(function (root) {
    'use strict';
    // Mengubah teks ukuran menjadi nilai meter.
    function parse(text) {
        const clean = text.toLowerCase().replace(/[сҫ]/g, 'c').replace(/\s/g, '');
        const match = clean.match(/^(\d+(?:[.,]\d+)*)(cm|mm|m)$/);
        if (!match) return null;
        let number = match[1];
        // Indonesian centimetre labels: 1.825 cm = 1825 cm. Comma is decimal.
        if (match[2] !== 'm' && /^\d{1,3}(\.\d{3})+$/.test(number))
            number = number.replace(/\./g, '');
        else if (number.includes(','))
            number = number.replace(/\./g, '').replace(',', '.');
        const value =
            Number(number) / (match[2] === 'cm' ? 100 : match[2] === 'mm' ? 1000 : 1);
        return Number.isFinite(value) && value > 0 && value <= 10000 ? value : null;
    }
    // Mencocokkan hasil pembacaan ukuran dengan denah.
    function interpret(readings, points, imageWidth, imageHeight) {
        if (!root.RoomGeometry.valid(points)) return null;
        const b = root.RoomGeometry.bounds(points),
            w = b.maxX - b.minX,
            h = b.maxY - b.minY;
        const values = [];
        for (const r of readings) {
            const metres = parse(r.text);
            if (metres === null) continue;
            const item = {
                ...r,
                metres,
                cx: (r.x + r.width / 2) * imageWidth,
                cy: (r.y + r.height / 2) * imageHeight,
                vertical: r.height * imageHeight > r.width * imageWidth
            };
            const duplicate = values.find(
                (a) =>
                    a.vertical === item.vertical &&
                    Math.abs(a.cx - item.cx) < 20 &&
                    Math.abs(a.cy - item.cy) < 20
            );
            if (duplicate) {
                if ((r.confidence || 0) > (duplicate.confidence || 0))
                    Object.assign(duplicate, item);
            } else values.push(item);
        }
        // Prefer a dimension beneath the outer baseline; do not mistake a partial top edge for total width.
        let horizontal = values.filter(
            (a) =>
                !a.vertical &&
                a.cy >= b.maxY &&
                a.cy <= b.maxY + h * 0.3 &&
                a.cx >= b.minX &&
                a.cx <= b.maxX
        );
        if (!horizontal.length) {
            // A top annotation represents the full width only when an outer edge spans that width.
            const fullTop = points.some((p, i) => {
                const q = points[(i + 1) % points.length];
                return (
                    Math.abs(p[0] - q[0]) >= w * 0.95 &&
                    Math.abs(p[1] - q[1]) < h * 0.04 &&
                    Math.max(p[1], q[1]) < b.minY + h * 0.1
                );
            });
            if (fullTop)
                horizontal = values.filter(
                    (a) =>
                        !a.vertical &&
                        a.cy < b.minY &&
                        a.cy >= b.minY - h * 0.35 &&
                        a.cx >= b.minX &&
                        a.cx <= b.maxX
                );
        }
        if (!horizontal.length)
            return {
                values,
                error: 'Panjang total belum terbaca. Pilih garis dan masukkan ukurannya.'
            };
        const widths = [...new Set(horizontal.map((a) => a.metres))];
        if (widths.length !== 1)
            return {
                values,
                error: 'Panjang total terbaca lebih dari satu nilai. Periksa ukuran horizontal.'
            };
        const sides = values.filter(
            (a) =>
                a.vertical &&
                a.cy > b.minY &&
                a.cy < b.maxY &&
                ((a.cx < b.minX && a.cx > b.minX - w * 0.3) ||
                    (a.cx > b.maxX && a.cx < b.maxX + w * 0.3))
        );
        if (!sides.length)
            return {
                values,
                error: 'Tinggi sisi luar belum terbaca. Isi lebar maksimum secara manual.'
            };
        const height = Math.max(...sides.map((a) => a.metres));
        const dimensions = { width: widths[0], height, values, adjustments: [] };
        const vertical = values.filter(
            (a) =>
                a.vertical &&
                a.cy > b.minY &&
                a.cy < b.maxY &&
                a.metres <= height &&
                a.cx > b.minX - w * 0.3 &&
                a.cx < b.maxX + w * 0.3
        );
        for (const r of vertical) {
            const candidates = points
                .map((p, index) => ({ index, p }))
                .filter(
                    (a) => a.p[1] < b.maxY - h * 0.15 && Math.abs(a.p[0] - r.cx) < w * 0.1
                )
                .sort((a, c) => Math.abs(a.p[0] - r.cx) - Math.abs(c.p[0] - r.cx));
            if (candidates.length) {
                dimensions.adjustments.push({
                    index: candidates[0].index,
                    y: b.minY + ((height - r.metres) / height) * h
                });
            }
        }
        return dimensions;
    }
    // Solve coordinate distances from labels near horizontal/vertical lines.
    // A slope is derived from its endpoints, never treated as a height label.
    function lineDimensions(readings, points, imageWidth, imageHeight) {
        const b=root.RoomGeometry.bounds(points), w=b.maxX-b.minX, h=b.maxY-b.minY;
        const info=interpret(readings,points,imageWidth,imageHeight);
        const values=info?.values || [];
        const groups=[[],[]], vertexGroups=[[],[]];
        for(let axis=0;axis<2;axis++) {
            const tolerance=(axis===0?w:h)*.015;
            const sorted=points.map((p,i)=>({value:p[axis],i})).sort((a,b)=>a.value-b.value);
            for(const item of sorted){
                let g=groups[axis].length-1;
                if(g<0 || item.value-groups[axis][g]>tolerance){groups[axis].push(item.value);g++;}
                vertexGroups[axis][item.i]=g;
            }
        }
        const candidates=[];
        for(let i=0;i<points.length;i++){
            const j=(i+1)%points.length;
            for(let axis=0;axis<2;axis++){
                const other=1-axis;
                if(vertexGroups[other][i]===vertexGroups[other][j] && vertexGroups[axis][i]!==vertexGroups[axis][j])
                    candidates.push({axis,a:vertexGroups[axis][i],z:vertexGroups[axis][j],p:points[i],q:points[j],label:`Sisi ${i+1}`});
            }
        }
        // Internal height markers may connect an upper corner to a full-width base.
        const fullBase=candidates.some(c=>c.axis===0 && Math.abs(c.p[1]-b.maxY)<h*.02 && Math.abs(c.q[0]-c.p[0])>w*.95);
        if(fullBase) for(let i=0;i<points.length;i++){
            const p=points[i];
            if(p[0]>b.minX+w*.02 && p[0]<b.maxX-w*.02 && p[1]<b.maxY-h*.05)
                candidates.push({axis:1,a:vertexGroups[1][i],z:groups[1].length-1,p,q:[p[0],b.maxY],label:`Tinggi titik ${i+1} ke dasar`});
        }
        const equations=[[],[]], matched=[];
        for(const value of values){
            const axis=value.vertical?1:0, other=1-axis, center=[value.cx,value.cy];
            const ranked=candidates.filter(c=>c.axis===axis).map(c=>{
                const lo=Math.min(c.p[axis],c.q[axis]), hi=Math.max(c.p[axis],c.q[axis]);
                const span=hi-lo;
                if(center[axis]<lo-span*.12 || center[axis]>hi+span*.12)return null;
                const distance=Math.abs(center[other]-(c.p[other]+c.q[other])/2)/(axis===0?h:w);
                if(distance>.22)return null;
                return {c,score:distance+.12*Math.abs(center[axis]-(lo+hi)/2)/span};
            }).filter(Boolean).sort((a,b)=>a.score-b.score);
            if(!ranked.length)continue;
            const best=ranked[0];
            if(ranked[1] && ranked[1].score-best.score<.018 &&
                [best.c.a,best.c.z].sort().join()!==[ranked[1].c.a,ranked[1].c.z].sort().join())
                return {error:'Ada angka yang cocok dengan lebih dari satu garis. Pilih sisi tersebut secara manual.'};
            equations[axis].push({from:Math.min(best.c.a,best.c.z),to:Math.max(best.c.a,best.c.z),length:value.metres});
            matched.push({label:best.c.label,metres:value.metres});
        }
        const solved=[];
        for(let axis=0;axis<2;axis++){
            const coords=Array(groups[axis].length).fill(null);coords[0]=0;
            let changed=true;
            while(changed){changed=false;
                for(const e of equations[axis]){
                    if(coords[e.from]!==null && coords[e.to]===null){coords[e.to]=coords[e.from]+e.length;changed=true;}
                    else if(coords[e.to]!==null && coords[e.from]===null){coords[e.from]=coords[e.to]-e.length;changed=true;}
                    else if(coords[e.from]!==null && Math.abs(coords[e.to]-coords[e.from]-e.length)>1e-6)
                        return {error:'Angka pada garis saling bertentangan. Periksa ukuran secara manual.'};
                }
            }
            if(coords.some(v=>v===null))return {error:'Angka belum cukup untuk menentukan semua sudut. Lengkapi ukuran garis secara manual.'};
            if(coords.some((v,i)=>i>0 && v<=coords[i-1]))return {error:'Ukuran tidak sesuai urutan garis pada gambar. Periksa ukuran manual.'};
            solved.push(coords);
        }
        const physical=points.map((p,i)=>[solved[0][vertexGroups[0][i]],solved[1][vertexGroups[1][i]]].map(v=>Number(v.toFixed(10))));
        if(!root.RoomGeometry.valid(physical))return {error:'Ukuran tidak membentuk batas ruangan yang valid.'};
        const width=solved[0].at(-1),height=solved[1].at(-1);
        if(width>10000 || height>10000)return {error:'Ukuran ruangan di luar batas.'};
        return {width,height,points:physical,matched};
    }
    // Rectangles use exact totals; other outlines use line-linked coordinate constraints.
    function automatic(readings, points, imageWidth, imageHeight) {
        if (!Array.isArray(readings) || !root.RoomGeometry.valid(points)) return null;
        const b = root.RoomGeometry.bounds(points);
        const w = b.maxX - b.minX, h = b.maxY - b.minY;
        const clean = readings.filter(r => r && typeof r.text === 'string' &&
            [r.x,r.y,r.width,r.height,r.confidence].every(Number.isFinite) && r.confidence >= .5);
        if (points.length !== 4 || !points.every((p, i) => {
            const q = points[(i + 1) % points.length];
            return (Math.abs(p[0]-q[0]) < w*.04 && Math.abs(p[1]-q[1]) > h*.95)
                || (Math.abs(p[1]-q[1]) < h*.04 && Math.abs(p[0]-q[0]) > w*.95);
        })) return lineDimensions(clean,points,imageWidth,imageHeight);
        const result = interpret(clean, points, imageWidth, imageHeight);
        if (!result || result.error) return result;
        const sideValues = result.values.filter(a => a.vertical && a.cy>b.minY && a.cy<b.maxY &&
            ((a.cx<b.minX && a.cx>b.minX-w*.3) || (a.cx>b.maxX && a.cx<b.maxX+w*.3)));
        const horizontalValues = result.values.filter(a => !a.vertical && a.cx>b.minX && a.cx<b.maxX &&
            ((a.cy<b.minY && a.cy>b.minY-h*.35) || (a.cy>b.maxY && a.cy<b.maxY+h*.3)));
        if (new Set(sideValues.map(a=>a.metres)).size!==1 || new Set(horizontalValues.map(a=>a.metres)).size!==1)
            return {error:'Angka ukuran saling berbeda. Gunakan ukuran manual per sisi.'};
        return {width:result.width,height:result.height};
    }
    root.DimensionReader = { parse, interpret, automatic };
    if (typeof module !== 'undefined') module.exports = root.DimensionReader;
})(typeof globalThis !== 'undefined' ? globalThis : this);
