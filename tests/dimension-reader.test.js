const assert=require('node:assert/strict');
global.RoomGeometry=require('../assets/js/room-geometry.js');
const D=require('../assets/js/dimension-reader.js');
assert.equal(D.parse('1.825cm'),18.25);
assert.equal(D.parse('1825 cm'),18.25);
assert.equal(D.parse('1.825сm'),18.25);
assert.equal(D.parse('250cm'),2.5);
assert.equal(D.parse('2,50 m'),2.5);
assert.equal(D.parse('Objek 3'),null);
const readings=[
{text:'1.825cm',x:.33,y:.733,width:.056,height:.027,confidence:.8},
{text:'250cm',x:.104,y:.38,width:.013,height:.1,confidence:.8},
{text:'220cm',x:.474,y:.38,width:.013,height:.1,confidence:.8},
{text:'140cm',x:.885,y:.48,width:.013,height:.1,confidence:.8}];
const points=[[117,84],[442,84],[818,142],[818,317],[117,317]];
const result=D.interpret(readings,points,935,441);
assert.equal(result.width,18.25);assert.equal(result.height,2.5);
assert.equal(result.adjustments.length,3);
assert.ok(Math.abs(result.adjustments.find(p=>p.index===2).y-(84+233*1.1/2.5))<1e-8);
assert.match(D.interpret(readings.slice(1),points,935,441).error,/Panjang total/);
console.log('OCR units, Indonesian thousands, vertical labels and measured heights passed.');

const rectangle=[[200,200],[600,200],[600,600],[200,600]];
const topReading=[{text:'400 cm',x:.35,y:.12,width:.12,height:.04,confidence:.9},{text:'800 cm',x:.12,y:.35,width:.04,height:.14,confidence:.9}];
const topResult=D.interpret(topReading,rectangle,1000,1000);
assert.equal(topResult.width,4);assert.equal(topResult.height,8);
console.log('Full-width dimension above rectangle passed.');
