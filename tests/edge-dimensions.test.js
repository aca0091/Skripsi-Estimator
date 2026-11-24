const assert=require('node:assert/strict');
const E=require('../assets/js/edge-dimensions.js');
const box=[[0,0],[100,0],[100,100],[0,100]];
let result=E.calibrate(box,{0:4},null);
assert.equal(result.complete,false);
result=E.calibrate(box,{0:4,1:8},null);
assert.equal(result.complete,true);assert.equal(result.x,.04);assert.equal(result.y,.08);
assert.equal(E.nearest(box,[50,2],10),0);assert.equal(E.nearest(box,[99,50],10),1);
assert.equal(E.nearest(box,[50,50],10),-1);
assert.throws(()=>E.calibrate(box,{0:4,2:5},null),/bertentangan/);
assert.throws(()=>E.calibrate(box,{0:0},null));
const slope=[[0,0],[3,4],[3,8],[0,8]];
result=E.calibrate(slope,{0:5,1:4},null);
assert.ok(Math.abs(result.x-1)<1e-10&&Math.abs(result.y-1)<1e-10);
console.log('Edge hit testing, axis and sloped calibration, missing scale and conflicting measurements passed.');

assert.equal(E.parseLength('4','m'),4);
assert.equal(E.parseLength('4 meter','cm'),4);
assert.equal(E.parseLength('4,5','m'),4.5);
assert.equal(E.parseLength('400 cm','m'),4);
assert.ok(Number.isNaN(E.parseLength('4x','m')));
const noisy=E.normalize([[100.2,50],[299.8,50.1],[300.2,250],[100,249.9]]);
const exact=E.calibrate(noisy,{0:4,1:8,2:4,3:8},null);
for(let i=0;i<4;i++){const a=noisy[i],b=noisy[(i+1)%4];assert.ok(Math.abs(Math.hypot((a[0]-b[0])*exact.x,(a[1]-b[1])*exact.y)-(i%2?8:4))<1e-10);}
console.log('Exact entered dimensions despite pixel noise, metre suffix and decimal comma passed.');

// A sketch is not necessarily to scale: independently measured heights must fit.
const sketch=[[115,82],[435,82],[819,141],[819,318],[115,318]];
for(const right of [1.4,2.5]){
    const fitted=E.fit(sketch,{2:right,4:2.5,3:18.25});
    assert.equal(fitted.complete,true);
    for(const [i,l] of [[2,right],[4,2.5],[3,18.25]]){
        const a=fitted.points[i],b=fitted.points[(i+1)%5];
        assert.ok(Math.abs(Math.hypot(a[0]-b[0],a[1]-b[1])-l)<1e-7);
    }
}
assert.equal(E.fit(sketch,{2:1.4,4:2.5}).complete,false);
console.log('Independent 140/250 cm heights and 1825 cm base fit an unscaled sketch.');
