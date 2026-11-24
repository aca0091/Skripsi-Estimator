const fs = require('fs'),
    vm = require('vm'),
    assert = require('assert/strict');
const html = fs.readFileSync('estimasi.php', 'utf8'),
    nodes = {};
const context = new Proxy(
    { measureText: (text) => ({ width: text.length * 7 }) },
    { get: (t, k) => t[k] || (() => {}), set: (t, k, v) => ((t[k] = v), true) }
);
function node(id = '') {
    return {
        id,
        value: '',
        checked: false,
        style: {},
        classList: { add() {}, remove() {} },
        append() {},
        replaceChildren() {},
        addEventListener() {},
        attributes: {},
        setAttribute(k, v) {
            this.attributes[k] = v;
        },
        getAttribute(k) {
            return this.attributes[k] ?? null;
        },
        getContext() {
            return context;
        },
        width: 1000,
        height: 470,
        textContent: '',
        innerText: '',
        getBoundingClientRect() {
            return { left: 0, top: 0, width: 1000, height: 470 };
        },
        setPointerCapture() {}
    };
}
for (const match of html.matchAll(/<[^>]+id="([^"]+)"[^>]*>/g)) {
    nodes[match[1]] = node(match[1]);
    nodes[match[1]].value = match[0].match(/value="([^"]*)"/)?.[1] || '';
}
const select = nodes.selectBahanKarpet;
select.value = '1';
select.selectedIndex = 0;
select.options = [
    { value: '1', dataset: { lebar: '4', harga: '480000', nama: 'Karpet' } }
];
select.selectedOptions = select.options;
nodes.txtPelanggan.value = '1';
nodes.txtPelanggan.selectedOptions = [{ textContent: 'Customer Uji (#1)' }];
const sandbox = {
    console,
    EdgeDimensions: require('../assets/js/edge-dimensions.js'),
    RoomGeometry: require(process.cwd() + '/assets/js/room-geometry.js'),
    window: {
        listeners: {},
        addEventListener(name, fn) {
            this.listeners[name] = fn;
        }
    },
    document: { getElementById: (id) => nodes[id] || null, createElement: () => node() },
    alert: (m) => {
        throw Error(m);
    },
    Image: function () {},
    FileReader: function () {}
};
vm.createContext(sandbox);
vm.runInContext(html.match(/<script>\s*([\s\S]*?)<\/script>/)[1], sandbox);
vm.runInContext(fs.readFileSync('assets/js/layout-editor.js', 'utf8'), sandbox);
vm.runInContext('window.onload()', sandbox);
assert.equal(nodes.post_luas_bersih.value, 0);
assert.ok(!html.includes('readImageDimensions(file'));
assert.ok(html.includes('dimension-reader.js')); 
vm.runInContext(
    'uploadedImageObj={width:935,height:441};isImageReady=true;acceptRoomPoints([[100.2,50],[299.8,50.1],[300.2,250],[100,249.9]]);selectRoomEdge(0)',
    sandbox
);
nodes.edgeUnit.value = 'm';
nodes.edgeLength.value = '4 meter';
vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
assert.match(vm.runInContext('measurementError()', sandbox), /arah/);
vm.runInContext('selectRoomEdge(1)', sandbox);
nodes.edgeLength.value = '8';
vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
vm.runInContext('selectRoomEdge(2)', sandbox);
nodes.edgeLength.value = '4';
vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
vm.runInContext('selectRoomEdge(3)', sandbox);
nodes.edgeLength.value = '8';
vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
const points = JSON.parse(nodes.post_layout_geometry.value).points;
points.forEach((a, i) => {
    const b = points[(i + 1) % points.length];
    assert.ok(Math.abs(Math.hypot(a[0] - b[0], a[1] - b[1]) - (i % 2 ? 8 : 4)) < 1e-10);
});
assert.equal(Number(nodes.post_luas_bersih.value), 32);
assert.equal(Number(nodes.post_obras.value), 24);
assert.equal(Number(nodes.dimPanjang.value), 4);
assert.equal(Number(nodes.dimLebar.value), 8);
vm.runInContext('goToStep(3);goToStep(4)', sandbox);
assert.ok(Number(nodes.post_biaya.value) > 0);
assert.match(nodes.resNamaPelanggan.innerText, /Customer Uji/);
vm.runInContext('selectRoomEdge(2)', sandbox);
nodes.edgeLength.value = '5';
vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
assert.match(nodes.edgeMeasurementStatus.textContent, /bertentangan/);
assert.equal(Number(nodes.post_luas_bersih.value), 32);
vm.runInContext('goToStep(1)', sandbox);
assert.equal(nodes.dimPanjang.value, '');
assert.equal(nodes.post_layout_geometry.value, '');
assert.equal(Number(nodes.post_biaya.value), 0);
assert.equal(vm.runInContext('Object.keys(edgeMeasurements).length', sandbox), 0);
sandbox.window.listeners.pageshow({ persisted: true });
assert.equal(vm.runInContext('currentStep', sandbox), 1);
console.log(
    'Manual-only flow: noisy rectangle -> exact 4/8/4/8 m -> area 32 -> perimeter 24 -> estimate -> reset passed.'
);
vm.runInContext(
    'uploadedImageObj={width:400,height:400};isImageReady=true;acceptRoomPoints([[0,0],[400,0],[400,400],[0,400]]);layoutObjects=[{points:[[10,20],[40,20],[40,160],[10,160]],width:0,height:0},{points:[[360,20],[390,20],[390,160],[360,160]],width:0,height:0}];renderObjectInputs();selectRoomEdge(0)',
    sandbox
);
nodes.edgeLength.value = '4';
vm.runInContext('applyRoomEdgeMeasurement();selectRoomEdge(1)', sandbox);
nodes.edgeLength.value = '4';
vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
assert.match(vm.runInContext('measurementError()', sandbox), /objek/);
nodes.edgeUnit.value = 'cm';
for (let i = 0; i < 2; i++) {
    vm.runInContext(`selectObjectEdge(${i},0)`, sandbox);
    nodes.edgeLength.value = '12';
    vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
    vm.runInContext(`selectObjectEdge(${i},1)`, sandbox);
    nodes.edgeLength.value = '139';
    vm.runInContext('applyRoomEdgeMeasurement()', sandbox);
}
assert.equal(Number(nodes.dimObjek.value), 0.3336);
assert.equal(Number(nodes.post_luas_bersih.value), 15.6664);
assert.equal(vm.runInContext('measurementError()', sandbox), '');
assert.equal(nodes.dimObjek.readOnly, true);
assert.equal(Number(nodes.dimPanjang.value), 4, 'Object sizes must not rescale the room');
vm.runInContext('resetLayoutEstimate()', sandbox);
assert.equal(vm.runInContext('layoutObjects.length', sandbox), 0);
assert.equal(nodes.dimObjek.readOnly, false);
console.log(
    'Object edges accept cm, update net area, preserve room scale and reset safely.'
);
// Polygon drafting: two points are insufficient; a third closes a triangle.
vm.runInContext(
    'uploadedImageObj={width:400,height:400};isImageReady=true;acceptRoomPoints([[0,0],[400,0],[400,400],[0,400]]);startObjectPolygon();addObjectPoint([20,20]);addObjectPoint([120,20]);finishObjectPolygon()',
    sandbox
);
assert.equal(vm.runInContext('objectDrawing', sandbox), true);
assert.equal(vm.runInContext('layoutObjects.length', sandbox), 0);
assert.match(nodes.objectDrawStatus.textContent, /minimal 3/);
vm.runInContext('addObjectPoint([20,170]);finishObjectPolygon()', sandbox);
assert.equal(vm.runInContext('layoutObjects[0].points.length', sandbox), 3);
nodes.edgeUnit.value = 'm';
nodes.edgeLength.value = '2';
vm.runInContext('applyObjectEdgeMeasurement();selectObjectEdge(0,2)', sandbox);
nodes.edgeLength.value = '3';
vm.runInContext('applyObjectEdgeMeasurement()', sandbox);
assert.equal(
    Number(nodes.dimObjek.value),
    3,
    'Triangle area must be half its bounding rectangle'
);
// Four individually clicked corners, closing by clicking the first point.
vm.runInContext(
    'startObjectPolygon();addObjectPoint([200,20]);addObjectPoint([300,20]);addObjectPoint([300,120]);addObjectPoint([200,120]);addObjectPoint([200,20])',
    sandbox
);
assert.equal(vm.runInContext('layoutObjects[1].points.length', sandbox), 4);
assert.equal(vm.runInContext('objectDrawing', sandbox), false);
// Concave six-corner shape, area 5 rather than its 3 x 3 bounding box.
vm.runInContext(
    'startObjectPolygon();[[20,200],[170,200],[170,250],[70,250],[70,350],[20,350]].forEach(addObjectPoint);finishObjectPolygon();layoutObjects[1].width=1;layoutObjects[1].height=1;layoutObjects[2].width=3;layoutObjects[2].height=3;syncObjectArea();selectObjectEdge(2,5)',
    sandbox
);
assert.equal(Number(nodes.dimObjek.value), 9);
assert.equal(
    Number(nodes.edgeLength.value),
    3,
    'Last edge must wrap using actual polygon vertex count'
);
// Crossing polygons remain drafts, and undo/cancel do not remove completed objects.
vm.runInContext(
    'startObjectPolygon();[[20,20],[120,120],[20,120],[120,20]].forEach(addObjectPoint);finishObjectPolygon()',
    sandbox
);
assert.equal(vm.runInContext('layoutObjects.length', sandbox), 3);
assert.equal(vm.runInContext('objectDrawing', sandbox), true);
vm.runInContext('undoObjectPoint()', sandbox);
assert.equal(vm.runInContext('objectDraft.length', sandbox), 3);
vm.runInContext('cancelObjectPolygon()', sandbox);
assert.equal(vm.runInContext('objectDraft.length', sandbox), 0);
assert.equal(vm.runInContext('layoutObjects.length', sandbox), 3);
vm.runInContext(
    'startObjectPolygon();addObjectPoint([20,20]);resetLayoutEstimate()',
    sandbox
);
assert.equal(vm.runInContext('objectDraft.length', sandbox), 0);
assert.equal(vm.runInContext('objectDrawing', sandbox), false);
console.log(
    'Triangle, four-corner closure, concave polygon area, last-edge selection, crossing rejection, undo, cancel and reset passed.'
);

// Automatic dimensions preview must not silently replace manual measurements.
Object.assign(sandbox, {AbortController, setTimeout, clearTimeout, FormData: class {append() {}}});
vm.runInContext(fs.readFileSync('assets/js/dimension-reader.js','utf8'), sandbox);
vm.runInContext(fs.readFileSync('assets/js/automatic-dimensions.js','utf8'), sandbox);
const ocrReadings = [
 {text:'400 cm',x:.4,y:.05,width:.2,height:.04,confidence:1},
 {text:'800 cm',x:.1,y:.4,width:.04,height:.2,confidence:1}
];
const ocrResponse = {ok:true,redirected:false,headers:{get:()=> 'application/json'},json:async()=>({readings:ocrReadings})};
(async()=>{
 vm.runInContext('uploadedImageObj={width:1000,height:1000};isImageReady=true;acceptRoomPoints([[200,150],[800,151],[799,750],[200,751]])',sandbox);
 nodes.inputLayoutFile.files=[{type:'image/png',size:1000}];
 nodes.dimPanjang.value='6';nodes.dimLebar.value='9';
 sandbox.BrowserDimensionOCR={read:async()=>ocrReadings};
 await vm.runInContext('readAutomaticDimensions()',sandbox);
 assert.equal(nodes.dimPanjang.value,'6');assert.equal(nodes.autoDimensionsPreview.hidden,false);
 vm.runInContext('applyAutomaticDimensions()',sandbox);
 assert.equal(Number(nodes.dimPanjang.value),4);assert.equal(Number(nodes.dimLebar.value),8);
 assert.equal(Number(nodes.post_luas_bersih.value),32);assert.equal(Number(nodes.post_obras.value),24);
 for (let i=0;i<4;i++) {
  vm.runInContext(`selectRoomEdge(${i})`,sandbox);
  assert.equal(Number(nodes.edgeLength.value),i%2?8:4);
 }
 assert.deepEqual(JSON.parse(nodes.post_layout_geometry.value).points,[[0,0],[4,0],[4,8],[0,8]]);
 vm.runInContext('selectRoomEdge(0)',sandbox);nodes.edgeLength.value='5';nodes.edgeUnit.value='m';
 vm.runInContext('applyRoomEdgeMeasurement()',sandbox);assert.equal(Number(nodes.dimPanjang.value),5);
 sandbox.BrowserDimensionOCR={read:async()=>{throw Error('OCR unavailable');}};
 await vm.runInContext('readAutomaticDimensions()',sandbox);assert.equal(Number(nodes.dimPanjang.value),5);
 assert.match(nodes.autoDimensionsStatus.textContent,/unavailable/);
 sandbox.BrowserDimensionOCR={read:async()=>{nodes.dimPanjang.value='7';return ocrReadings;}};
 await vm.runInContext('readAutomaticDimensions()',sandbox);
 assert.equal(nodes.autoDimensionsPreview.hidden,true);assert.match(nodes.autoDimensionsStatus.textContent,/berubah/);
 sandbox.BrowserDimensionOCR={read:async()=>ocrReadings};
 await vm.runInContext('readAutomaticDimensions()',sandbox);
 vm.runInContext('resetLayoutEstimate()',sandbox);vm.runInContext('applyAutomaticDimensions()',sandbox);
 assert.equal(nodes.dimPanjang.value,'');assert.equal(nodes.autoDimensionsPreview.hidden,true);
 const trapezoidFixture=require('./fixtures/trapezoid-readings.json');
 sandbox.fixture=trapezoidFixture;
 vm.runInContext('uploadedImageObj={width:1827,height:861};isImageReady=true;acceptRoomPoints(fixture.detected)',sandbox);
 sandbox.BrowserDimensionOCR={read:async()=>trapezoidFixture.readings};
 await vm.runInContext('readAutomaticDimensions()',sandbox);
 vm.runInContext('applyAutomaticDimensions()',sandbox);
 assert.ok(Math.abs(Number(nodes.post_luas_bersih.value)-42.72)<1e-9);
 assert.deepEqual(JSON.parse(nodes.post_layout_geometry.value).points,[[0,0],[9.6,0],[19.2,1.1],[19.2,2.5],[0,2.5]]);
 const expectedSides=[9.6,Math.hypot(9.6,1.1),1.4,19.2,2.5];
 for(let i=0;i<5;i++){
  vm.runInContext(`selectRoomEdge(${i})`,sandbox);
  assert.ok(Math.abs(Number(nodes.edgeLength.value)-expectedSides[i])<.00051);
 }
 assert.ok(Math.abs(Number(nodes.post_obras.value)-expectedSides.reduce((a,b)=>a+b,0))<1e-9);
 console.log('Trapezoid application preserves sloped shape, exact measured sides, area, perimeter and saved geometry.');
 console.log('OCR preview/apply gives 4x8m, 32m², 24m perimeter; manual override, failure preservation and stale/reset protection passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
