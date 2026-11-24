(function(root){
    'use strict';
    function parse(text){
        const clean=text.toLowerCase().replace(/[сҫ]/g,'c').replace(/\s/g,'');
        const match=clean.match(/^(\d+(?:[.,]\d+)*)(cm|mm|m)$/);
        if(!match)return null;
        let number=match[1];
        // Indonesian centimetre labels: 1.825 cm = 1825 cm. Comma is decimal.
        if(match[2]!=='m'&&/^\d{1,3}(\.\d{3})+$/.test(number))number=number.replace(/\./g,'');
        else if(number.includes(','))number=number.replace(/\./g,'').replace(',','.');
        const value=Number(number)/(match[2]==='cm'?100:match[2]==='mm'?1000:1);
        return Number.isFinite(value)&&value>0&&value<=10000?value:null;
    }
    function interpret(readings,points,imageWidth,imageHeight){
        if(!root.RoomGeometry.valid(points))return null;
        const b=root.RoomGeometry.bounds(points),w=b.maxX-b.minX,h=b.maxY-b.minY;
        const values=[];
        for(const r of readings){const metres=parse(r.text);if(metres===null)continue;
            const item={...r,metres,cx:(r.x+r.width/2)*imageWidth,cy:(r.y+r.height/2)*imageHeight,vertical:r.height*imageHeight>r.width*imageWidth};
            const duplicate=values.find(a=>a.vertical===item.vertical&&Math.abs(a.cx-item.cx)<20&&Math.abs(a.cy-item.cy)<20);
            if(duplicate){if((r.confidence||0)>(duplicate.confidence||0))Object.assign(duplicate,item);}else values.push(item);
        }
        // Prefer a dimension beneath the outer baseline; do not mistake a partial top edge for total width.
        let horizontal=values.filter(a=>!a.vertical&&a.cy>=b.maxY&&a.cy<=b.maxY+h*.3&&a.cx>=b.minX&&a.cx<=b.maxX);
        if(!horizontal.length){
            // A top annotation represents the full width only when an outer edge spans that width.
            const fullTop=points.some((p,i)=>{const q=points[(i+1)%points.length];return Math.abs(p[0]-q[0])>=w*.95&&Math.abs(p[1]-q[1])<h*.04&&Math.max(p[1],q[1])<b.minY+h*.1;});
            if(fullTop)horizontal=values.filter(a=>!a.vertical&&a.cy<b.minY&&a.cy>=b.minY-h*.35&&a.cx>=b.minX&&a.cx<=b.maxX);
        }
        if(!horizontal.length)return {values,error:'Panjang total belum terbaca. Pilih garis dan masukkan ukurannya.'};
        const widths=[...new Set(horizontal.map(a=>a.metres))];
        if(widths.length!==1)return {values,error:'Panjang total terbaca lebih dari satu nilai. Periksa ukuran horizontal.'};
        const sides=values.filter(a=>a.vertical&&a.cy>b.minY&&a.cy<b.maxY&&((a.cx<b.minX&&a.cx>b.minX-w*.3)||(a.cx>b.maxX&&a.cx<b.maxX+w*.3)));
        if(!sides.length)return {values,error:'Tinggi sisi luar belum terbaca. Isi lebar maksimum secara manual.'};
        const height=Math.max(...sides.map(a=>a.metres));
        const dimensions={width:widths[0],height,values,adjustments:[]};
        const vertical=values.filter(a=>a.vertical&&a.cy>b.minY&&a.cy<b.maxY&&a.metres<=height&&a.cx>b.minX-w*.3&&a.cx<b.maxX+w*.3);
        for(const r of vertical){
            const candidates=points.map((p,index)=>({index,p})).filter(a=>a.p[1]<b.maxY-h*.15&&Math.abs(a.p[0]-r.cx)<w*.1).sort((a,c)=>Math.abs(a.p[0]-r.cx)-Math.abs(c.p[0]-r.cx));
            if(candidates.length){dimensions.adjustments.push({index:candidates[0].index,y:b.minY+(height-r.metres)/height*h});}
        }
        return dimensions;
    }
    root.DimensionReader={parse,interpret};
    if(typeof module!=='undefined')module.exports=root.DimensionReader;
})(typeof globalThis!=='undefined'?globalThis:this);
