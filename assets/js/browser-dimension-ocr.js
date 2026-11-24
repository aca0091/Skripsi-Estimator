(function (root) {
    'use strict';
    function unrotate(x, y, angle) {
        if (angle === 90) return [y, 1 - x];
        if (angle === -90) return [1 - y, x];
        return [x, y];
    }
    function readingsFromBlocks(blocks, width, height, angle) {
        const readings = [];
        for (const block of blocks || []) for (const paragraph of block.paragraphs || []) {
            for (const line of paragraph.lines || []) {
                const b = line.bbox;
                if (!b || typeof line.text !== 'string') continue;
                const corners = [[b.x0,b.y0],[b.x1,b.y0],[b.x1,b.y1],[b.x0,b.y1]]
                    .map(([x,y]) => unrotate(x/width, y/height, angle));
                const xs = corners.map(p=>p[0]), ys = corners.map(p=>p[1]);
                const x = Math.min(...xs), y = Math.min(...ys);
                readings.push({text:line.text.trim(), confidence:line.confidence/100,
                    x,y,width:Math.max(...xs)-x,height:Math.max(...ys)-y,rotation:angle});
            }
        }
        return readings;
    }
    async function refineLines(worker, image, width, height, blocks) {
        let attempts=0;
        for (const block of blocks || []) for (const paragraph of block.paragraphs || []) {
            for (const line of paragraph.lines || []) {
                if (line.confidence>=50 || !line.bbox || !/\d.*(?:cm|mm|m)/i.test(line.text) || attempts>=6) continue;
                attempts++;
                const b=line.bbox, pad=10;
                const left=Math.max(0,b.x0-pad), top=Math.max(0,b.y0-pad);
                await worker.setParameters({tessedit_pageseg_mode:'7'});
                const retry=await worker.recognize(image,{rectangle:{left,top,
                    width:Math.min(width,b.x1+pad)-left,height:Math.min(height,b.y1+pad)-top}});
                if(retry.data.confidence>line.confidence){line.text=retry.data.text;line.confidence=retry.data.confidence;}
            }
        }
        await worker.setParameters({tessedit_pageseg_mode:'11'});
        return blocks;
    }
    function rotatedCanvas(image, angle) {
        const scale = Math.min(1, 2000/Math.max(image.width,image.height));
        const width = Math.round(image.width*scale), height = Math.round(image.height*scale);
        const canvas = document.createElement('canvas');
        canvas.width = angle ? height : width;
        canvas.height = angle ? width : height;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';ctx.fillRect(0,0,canvas.width,canvas.height);
        ctx.translate(canvas.width/2,canvas.height/2);ctx.rotate(angle*Math.PI/180);
        ctx.drawImage(image,-width/2,-height/2,width,height);
        return canvas;
    }
    async function read(image, {signal, onProgress = ()=>{}} = {}) {
        if (!root.Tesseract) throw Error('Pustaka OCR belum termuat. Muat ulang halaman atau gunakan ukuran manual.');
        if (image.width*image.height > 24000000) throw Error('Gambar maksimal 24 megapiksel untuk pembacaan otomatis.');
        let worker;
        let stopped = false;
        let rejectFailure;
        const failure = new Promise((_,reject)=>{rejectFailure=reject;});
        const stop = () => {
            stopped = true;
            rejectFailure(new DOMException('Pembacaan dibatalkan.', 'AbortError'));
            if (worker) { void worker.terminate().catch(()=>{}); worker=null; }
        };
        signal?.addEventListener('abort',stop,{once:true});
        const work = async () => {
            if (signal?.aborted) throw new DOMException('Pembacaan dibatalkan.','AbortError');
            const base = new URL('assets/vendor/tesseract/', document.baseURI).href;
            onProgress('Menyiapkan pembaca angka lokal…');
            worker = await root.Tesseract.createWorker('eng',1,{
                workerPath:base+'worker.min.js',corePath:base+'core',langPath:base+'lang',
                workerBlobURL:false,
                errorHandler:error=>rejectFailure(new Error(String(error)))
            });
            if (stopped) { await worker.terminate();worker=null;return []; }
            await worker.setParameters({tessedit_pageseg_mode:'11'});
            const readings=[];
            for (const [index,angle] of [0,90,-90].entries()) {
                if (signal?.aborted) throw new DOMException('Pembacaan dibatalkan.','AbortError');
                onProgress(`Membaca angka pada gambar (${index+1}/3)…`);
                const canvas=rotatedCanvas(image,angle);
                const result=await worker.recognize(canvas,{}, {text:true,blocks:true});
                const blocks=await refineLines(worker,canvas,canvas.width,canvas.height,result.data.blocks);
                readings.push(...readingsFromBlocks(blocks,canvas.width,canvas.height,angle));
            }
            return readings;
        };
        try { return await Promise.race([work(),failure]); }
        finally {
            stopped = true;
            signal?.removeEventListener('abort',stop);
            if (worker) { const active=worker;worker=null;await active.terminate().catch(()=>{}); }
        }
    }
    root.BrowserDimensionOCR={read,readingsFromBlocks,unrotate,refineLines};
    if(typeof module!=='undefined') module.exports=root.BrowserDimensionOCR;
})(typeof globalThis!=='undefined'?globalThis:this);
