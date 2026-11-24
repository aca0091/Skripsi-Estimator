const assert = require('node:assert/strict');
global.RoomGeometry = require('../assets/js/room-geometry.js');
const D = require('../assets/js/dimension-reader.js');
assert.equal(D.parse('1.825cm'), 18.25);
assert.equal(D.parse('1825 cm'), 18.25);
assert.equal(D.parse('1.825сm'), 18.25);
assert.equal(D.parse('250cm'), 2.5);
assert.equal(D.parse('2,50 m'), 2.5);
assert.equal(D.parse('Objek 3'), null);
const readings = [
    { text: '1.825cm', x: 0.33, y: 0.733, width: 0.056, height: 0.027, confidence: 0.8 },
    { text: '250cm', x: 0.104, y: 0.38, width: 0.013, height: 0.1, confidence: 0.8 },
    { text: '220cm', x: 0.474, y: 0.38, width: 0.013, height: 0.1, confidence: 0.8 },
    { text: '140cm', x: 0.885, y: 0.48, width: 0.013, height: 0.1, confidence: 0.8 }
];
const points = [
    [117, 84],
    [442, 84],
    [818, 142],
    [818, 317],
    [117, 317]
];
const result = D.interpret(readings, points, 935, 441);
assert.equal(result.width, 18.25);
assert.equal(result.height, 2.5);
assert.equal(result.adjustments.length, 3);
assert.ok(
    Math.abs(result.adjustments.find((p) => p.index === 2).y - (84 + (233 * 1.1) / 2.5)) <
        1e-8
);
assert.match(D.interpret(readings.slice(1), points, 935, 441).error, /Panjang total/);
console.log(
    'OCR units, Indonesian thousands, vertical labels and measured heights passed.'
);

const rectangle = [
    [200, 200],
    [600, 200],
    [600, 600],
    [200, 600]
];
const topReading = [
    { text: '400 cm', x: 0.35, y: 0.12, width: 0.12, height: 0.04, confidence: 0.9 },
    { text: '800 cm', x: 0.12, y: 0.35, width: 0.04, height: 0.14, confidence: 0.9 }
];
const topResult = D.interpret(topReading, rectangle, 1000, 1000);
assert.equal(topResult.width, 4);
assert.equal(topResult.height, 8);
console.log('Full-width dimension above rectangle passed.');
const autoPoints=[[200,150],[800,150],[800,750],[200,750]];
const autoReadings=[{text:'400 cm',x:.4,y:.05,width:.2,height:.04,confidence:1},{text:'800 cm',x:.1,y:.4,width:.04,height:.2,confidence:1}];
assert.deepEqual(D.automatic(autoReadings,autoPoints,1000,1000),{width:4,height:8});
assert.ok(D.automatic([...autoReadings,{text:'600 cm',x:.85,y:.4,width:.04,height:.2,confidence:1}],autoPoints,1000,1000).error);
assert.ok(D.automatic([],autoPoints,1000,1000).error);
assert.ok(D.automatic(autoReadings,[[0,0],[100,0],[100,50],[50,50],[50,100],[0,100]],1000,1000).error);
console.log('Automatic size checks reject conflicting, missing and partial/complex dimensions.');
const fixture = require('./fixtures/trapezoid-readings.json');
const trapezoid = D.automatic(fixture.readings,fixture.detected,fixture.imageWidth,fixture.imageHeight);
assert.deepEqual(trapezoid.points,[[0,0],[9.6,0],[19.2,1.1],[19.2,2.5],[0,2.5]]);
const area=Math.abs(trapezoid.points.reduce((sum,p,i)=>{const q=trapezoid.points[(i+1)%5];return sum+p[0]*q[1]-q[0]*p[1];},0))/2;
assert.ok(Math.abs(area-42.72)<1e-10);
const reverse=D.automatic(fixture.readings,fixture.detected.slice().reverse(),fixture.imageWidth,fixture.imageHeight);
assert.deepEqual(reverse.points,trapezoid.points.slice().reverse());
const noTop=fixture.readings.filter(r=>r.text!=='960 cm');
assert.ok(D.automatic(noTop,fixture.detected,fixture.imageWidth,fixture.imageHeight).error);
const internalHeight={text:'250 cm',confidence:.95,x:890/1827,y:310/861,width:50/1827,height:220/861};
assert.deepEqual(D.automatic([...fixture.readings,internalHeight],fixture.detected,1827,861).points,trapezoid.points);
assert.ok(D.automatic([...fixture.readings,{...internalHeight,text:'350 cm'}],fixture.detected,1827,861).error);
console.log('Actual trapezoid OCR fixture: exact line coordinates, 42.72m², reverse order, internal heights and insufficient/conflicting labels passed.');
