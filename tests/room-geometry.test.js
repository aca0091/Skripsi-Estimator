const assert = require('node:assert/strict');
const G = require('../assets/js/room-geometry.js');
const near = (a, b) => assert.ok(Math.abs(a - b) < 1e-5, `${a} != ${b}`);
const rectangle = [
    [0, 0],
    [10, 0],
    [10, 6],
    [0, 6]
];
near(G.area(rectangle), 60);
near(G.perimeter(rectangle), 32);
assert.deepEqual(G.strips(rectangle, 4, 0), { count: 3, length: 18, seams: 12 });
const slope = [
    [0, 0],
    [4, 0],
    [10, 2],
    [10, 4],
    [0, 4]
];
near(G.area(slope), 34);
near(G.perimeter(slope), 10 + 4 + 4 + Math.sqrt(40) + 2);
const plan = G.strips(slope, 4, 0);
near(plan.length, 32 / 3);
near(plan.seams, 20 / 3);
assert.ok(plan.length < 12); // Sloped final strip must not use full bounding-box length.
const concave = [
    [0, 0],
    [6, 0],
    [6, 2],
    [2, 2],
    [2, 6],
    [0, 6]
];
near(G.area(concave), 20);
near(G.strips(concave, 2, 0).length, 10);
assert.equal(
    G.valid([
        [0, 0],
        [4, 4],
        [0, 4],
        [4, 0]
    ]),
    false
);
assert.equal(
    G.valid([
        [0, 0],
        [4, 0],
        [4, 0],
        [0, 4]
    ]),
    false
);
assert.equal(G.valid([]), false);
// Raster with a sloped roof, an open dimension line and an isolated annotation.
const w = 240,
    h = 140,
    data = new Uint8Array(w * h * 4).fill(255);
function line(a, b) {
    const n = Math.ceil(Math.hypot(b[0] - a[0], b[1] - a[1]));
    for (let i = 0; i <= n; i++) {
        const x = Math.round(a[0] + ((b[0] - a[0]) * i) / n),
            y = Math.round(a[1] + ((b[1] - a[1]) * i) / n),
            k = (y * w + x) * 4;
        data[k] = data[k + 1] = data[k + 2] = 0;
    }
}
const walls = [
    [20, 20],
    [100, 20],
    [220, 50],
    [220, 120],
    [20, 120]
];
walls.forEach((a, i) => line(a, walls[(i + 1) % walls.length]));
line([100, 35], [100, 105]);
line([80, 8], [100, 8]);
const detected = G.detect(data, w, h);
assert.equal(detected.length, 5);
for (const a of walls)
    assert.ok(detected.some((b) => Math.hypot(a[0] - b[0], a[1] - b[1]) < 5));
assert.deepEqual(G.detect(new Uint8Array(w * h * 4).fill(255), w, h), []);
console.log('Geometry, strips, concavity, invalid outlines and raster detection passed.');

// Connected inner boxes must not pull the outside boundary inward.
data.fill(255);
const stepped = [
    [50, 15],
    [180, 15],
    [180, 40],
    [210, 40],
    [210, 80],
    [180, 80],
    [180, 125],
    [155, 125],
    [155, 105],
    [50, 105],
    [50, 80],
    [25, 80],
    [25, 40],
    [50, 40]
];
stepped.forEach((a, i) => line(a, stepped[(i + 1) % stepped.length]));
[
    [
        [25, 48],
        [35, 48]
    ],
    [
        [35, 48],
        [35, 72]
    ],
    [
        [35, 72],
        [25, 72]
    ],
    [
        [210, 48],
        [200, 48]
    ],
    [
        [200, 48],
        [200, 72]
    ],
    [
        [200, 72],
        [210, 72]
    ],
    [
        [100, 50],
        [115, 50]
    ]
].forEach(([a, b]) => line(a, b));
const outer = G.detect(data, w, h);
assert.equal(outer.length, 14);
for (const a of stepped)
    assert.ok(outer.some((b) => Math.hypot(a[0] - b[0], a[1] - b[1]) < 5));
assert.ok(G.valid(outer));
console.log('Stepped outer walls retain 14 corners despite connected inner boxes.');
const objects = G.detectObjects(data, w, h, outer);
assert.equal(
    objects.length,
    2,
    'Detect both boxes attached to outer wall, ignoring open annotation'
);
objects.forEach((p) => assert.ok(G.valid(p)));
assert.deepEqual(G.detectObjects(new Uint8Array(w * h * 4).fill(255), w, h, []), []);
// A free-standing object is also detected; open lines and small text holes are ignored.
[
    [
        [80, 45],
        [95, 45]
    ],
    [
        [95, 45],
        [95, 70]
    ],
    [
        [95, 70],
        [80, 70]
    ],
    [
        [80, 70],
        [80, 45]
    ],
    [
        [120, 60],
        [122, 60]
    ],
    [
        [122, 60],
        [122, 63]
    ],
    [
        [122, 63],
        [120, 63]
    ],
    [
        [120, 63],
        [120, 60]
    ]
].forEach(([a, b]) => line(a, b));
assert.equal(G.detectObjects(data, w, h, G.detect(data, w, h)).length, 3);
console.log(
    'Wall-attached and free-standing object candidates detected without text holes.'
);
