/* Geometry shared by layout detection, estimates and printed previews. */
(function (root) {
    'use strict';
    const area = (p) =>
        Math.abs(
            p.reduce((s, a, i) => {
                const b = p[(i + 1) % p.length];
                return s + a[0] * b[1] - b[0] * a[1];
            }, 0)
        ) / 2;
    const perimeter = (p) =>
        p.reduce((s, a, i) => {
            const b = p[(i + 1) % p.length];
            return s + Math.hypot(a[0] - b[0], a[1] - b[1]);
        }, 0);
    // Mengambil batas koordinat poligon.
    function bounds(p) {
        return {
            minX: Math.min(...p.map((a) => a[0])),
            maxX: Math.max(...p.map((a) => a[0])),
            minY: Math.min(...p.map((a) => a[1])),
            maxY: Math.max(...p.map((a) => a[1]))
        };
    }
    // Memeriksa bentuk dan persilangan batas ruangan.
    function valid(p) {
        if (
            !Array.isArray(p) ||
            p.length < 3 ||
            p.length > 100 ||
            !p.every(
                (a) => Array.isArray(a) && a.length === 2 && a.every(Number.isFinite)
            ) ||
            area(p) < 1e-8
        )
            return false;
        const cross = (a, b, c) =>
            (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0]);
        const on = (a, b, c) =>
            Math.abs(cross(a, b, c)) < 1e-9 &&
            c[0] >= Math.min(a[0], b[0]) - 1e-9 &&
            c[0] <= Math.max(a[0], b[0]) + 1e-9 &&
            c[1] >= Math.min(a[1], b[1]) - 1e-9 &&
            c[1] <= Math.max(a[1], b[1]) + 1e-9;
        for (let i = 0; i < p.length; i++) {
            const a = p[i],
                b = p[(i + 1) % p.length];
            if (Math.hypot(a[0] - b[0], a[1] - b[1]) < 1e-8) return false;
            for (let j = i + 1; j < p.length; j++) {
                if (j === i + 1 || (i === 0 && j === p.length - 1)) continue;
                const c = p[j],
                    d = p[(j + 1) % p.length];
                if (
                    (cross(a, b, c) * cross(a, b, d) < 0 &&
                        cross(c, d, a) * cross(c, d, b) < 0) ||
                    on(a, b, c) ||
                    on(a, b, d) ||
                    on(c, d, a) ||
                    on(c, d, b)
                )
                    return false;
            }
        }
        return true;
    }
    // Memotong poligon pada batas koordinat.
    function clip(p, axis, value, greater) {
        const out = [];
        if (!p.length) return out;
        for (let i = 0; i < p.length; i++) {
            const a = p[i],
                b = p[(i + 1) % p.length],
                ia = greater ? a[axis] >= value : a[axis] <= value,
                ib = greater ? b[axis] >= value : b[axis] <= value;
            if (ia) out.push(a);
            if (ia !== ib) {
                const t = (value - a[axis]) / (b[axis] - a[axis]);
                out.push([a[0] + t * (b[0] - a[0]), a[1] + t * (b[1] - a[1])]);
            }
        }
        return out;
    }
    // Menghitung panjang penampang poligon.
    function section(p, axis, value) {
        const hits = [];
        for (let i = 0; i < p.length; i++) {
            const a = p[i],
                b = p[(i + 1) % p.length];
            if (
                (a[axis] <= value && b[axis] > value) ||
                (b[axis] <= value && a[axis] > value)
            )
                hits.push(
                    a[1 - axis] +
                        ((value - a[axis]) / (b[axis] - a[axis])) *
                            (b[1 - axis] - a[1 - axis])
                );
        }
        hits.sort((a, b) => a - b);
        let length = 0;
        for (let i = 0; i + 1 < hits.length; i += 2) length += hits[i + 1] - hits[i];
        return length;
    }
    // Menghitung jalur karpet dan panjang sambungan.
    function strips(p, width, axis) {
        if (!valid(p) || !(width > 0)) return { count: 0, length: 0, seams: 0 };
        const b = bounds(p),
            start = axis === 0 ? b.minX : b.minY,
            end = axis === 0 ? b.maxX : b.maxY;
        const count = Math.ceil((end - start) / width - 1e-10);
        if (count > 10000)
            throw new Error('Jumlah jalur terlalu besar. Periksa ukuran dan lebar roll.');
        let length = 0,
            seams = 0;
        for (let i = 0; i < count; i++) {
            const low = start + i * width,
                high = Math.min(end, low + width),
                part = clip(
                    clip(p, axis, low + Math.min(1e-9, width / 1000), true),
                    axis,
                    high - Math.min(1e-9, width / 1000),
                    false
                );
            if (part.length && area(part) > 1e-9) {
                const values = part.map((a) => a[1 - axis]);
                length += Math.max(...values) - Math.min(...values);
            }
            if (i > 0)
                seams += Math.min(
                    section(p, axis, low - 1e-7),
                    section(p, axis, low + 1e-7)
                );
        }
        return { count, length, seams };
    }
    // Menyederhanakan titik garis batas.
    function simplify(p, epsilon) {
        if (p.length <= 2) return p;
        const a = p[0],
            b = p[p.length - 1],
            dx = b[0] - a[0],
            dy = b[1] - a[1],
            den = dx * dx + dy * dy;
        let max = 0,
            index = 0;
        for (let i = 1; i < p.length - 1; i++) {
            const t = den
                ? Math.max(
                      0,
                      Math.min(1, ((p[i][0] - a[0]) * dx + (p[i][1] - a[1]) * dy) / den)
                  )
                : 0;
            const d = Math.hypot(p[i][0] - a[0] - t * dx, p[i][1] - a[1] - t * dy);
            if (d > max) {
                max = d;
                index = i;
            }
        }
        return max > epsilon
            ? simplify(p.slice(0, index + 1), epsilon)
                  .slice(0, -1)
                  .concat(simplify(p.slice(index), epsilon))
            : [a, b];
    }
    // Follow the outside of the main connected wall, excluding text and internal partitions.
    // Mendeteksi batas luar ruangan dari gambar.
    function detect(rgba, w, h) {
        const n = w * h,
            dark = new Uint8Array(n),
            wall = new Uint8Array(n),
            seen = new Uint8Array(n),
            queue = new Int32Array(n);
        for (let i = 0; i < n; i++)
            dark[i] = (rgba[i * 4] + rgba[i * 4 + 1] + rgba[i * 4 + 2]) / 3 < 190 ? 1 : 0;
        for (let y = 1; y < h - 1; y++)
            for (let x = 1; x < w - 1; x++) {
                const i = y * w + x;
                if (dark[i])
                    for (let dy = -1; dy <= 1; dy++)
                        for (let dx = -1; dx <= 1; dx++) wall[i + dy * w + dx] = 1;
            }
        const neighbors = (i) => {
            const x = i % w,
                y = Math.floor(i / w);
            return [
                x > 0 ? i - 1 : -1,
                x < w - 1 ? i + 1 : -1,
                y > 0 ? i - w : -1,
                y < h - 1 ? i + w : -1
            ];
        };
        let main = [];
        for (let seed = 0; seed < n; seed++) {
            if (!wall[seed] || seen[seed]) continue;
            let head = 0,
                tail = 1;
            queue[0] = seed;
            seen[seed] = 1;
            while (head < tail)
                for (const j of neighbors(queue[head++]))
                    if (j >= 0 && wall[j] && !seen[j]) {
                        seen[j] = 1;
                        queue[tail++] = j;
                    }
            if (tail > main.length) main = Array.from(queue.subarray(0, tail));
        }
        wall.fill(0);
        for (const i of main) wall[i] = 1;
        seen.fill(0);
        let head = 0,
            tail = 0;
        const seed = (i) => {
            if (!wall[i] && !seen[i]) {
                seen[i] = 1;
                queue[tail++] = i;
            }
        };
        for (let x = 0; x < w; x++) {
            seed(x);
            seed((h - 1) * w + x);
        }
        for (let y = 0; y < h; y++) {
            seed(y * w);
            seed(y * w + w - 1);
        }
        while (head < tail) for (const j of neighbors(queue[head++])) if (j >= 0) seed(j);
        const inside = new Uint8Array(n),
            best = [];
        let enclosed = 0;
        for (let i = 0; i < n; i++)
            if (!seen[i]) {
                inside[i] = 1;
                best.push(i);
                if (!wall[i]) enclosed++;
            }
        if (enclosed < n * 0.01) return [];
        const edges = new Map(),
            stride = w + 1;
        for (const i of best) {
            const x = i % w,
                y = Math.floor(i / w),
                a = y * stride + x;
            if (y === 0 || !inside[i - w]) edges.set(a, a + 1);
            if (x === w - 1 || !inside[i + 1]) edges.set(a + 1, a + stride + 1);
            if (y === h - 1 || !inside[i + w]) edges.set(a + stride + 1, a + stride);
            if (x === 0 || !inside[i - 1]) edges.set(a + stride, a);
        }
        let longest = [];
        while (edges.size) {
            const start = edges.keys().next().value;
            let at = start,
                loop = [];
            do {
                loop.push([at % stride, Math.floor(at / stride)]);
                const next = edges.get(at);
                edges.delete(at);
                at = next;
            } while (at !== undefined && at !== start && loop.length <= n);
            if (at === start && area(loop) > area(longest)) longest = loop;
        }
        if (longest.length < 3) return [];
        let far = 1;
        for (let i = 2; i < longest.length; i++)
            if (
                Math.hypot(longest[i][0] - longest[0][0], longest[i][1] - longest[0][1]) >
                Math.hypot(
                    longest[far][0] - longest[0][0],
                    longest[far][1] - longest[0][1]
                )
            )
                far = i;
        const eps = Math.max(2, Math.max(w, h) * 0.003);
        const result = simplify(longest.slice(0, far + 1), eps)
            .slice(0, -1)
            .concat(simplify(longest.slice(far).concat([longest[0]]), eps).slice(0, -1));
        return valid(result) ? result : [];
    }
    // Closed light regions other than the main floor are candidates, not semantic labels.
    // Keep wall-attached boxes; reject text holes and open annotation strokes.
    // Mendeteksi kandidat objek di dalam ruangan.
    function detectObjects(rgba, w, h, outline) {
        if (!valid(outline)) return [];
        const n = w * h,
            seen = new Uint8Array(n),
            queue = new Int32Array(n),
            regions = [];
        const dark = (i) => (rgba[i * 4] + rgba[i * 4 + 1] + rgba[i * 4 + 2]) / 3 < 190;
        const inside = (x, y) => {
            let yes = false;
            for (let i = 0, j = outline.length - 1; i < outline.length; j = i++) {
                const a = outline[i],
                    b = outline[j];
                if (
                    a[1] > y !== b[1] > y &&
                    x < ((b[0] - a[0]) * (y - a[1])) / (b[1] - a[1]) + a[0]
                )
                    yes = !yes;
            }
            return yes;
        };
        for (let seed = 0; seed < n; seed++) {
            if (seen[seed] || dark(seed)) continue;
            let head = 0,
                tail = 1,
                minX = w,
                maxX = 0,
                minY = h,
                maxY = 0,
                border = false;
            queue[0] = seed;
            seen[seed] = 1;
            while (head < tail) {
                const i = queue[head++],
                    x = i % w,
                    y = Math.floor(i / w);
                minX = Math.min(minX, x);
                maxX = Math.max(maxX, x);
                minY = Math.min(minY, y);
                maxY = Math.max(maxY, y);
                if (x === 0 || y === 0 || x === w - 1 || y === h - 1) border = true;
                for (const j of [
                    x > 0 ? i - 1 : -1,
                    x < w - 1 ? i + 1 : -1,
                    y > 0 ? i - w : -1,
                    y < h - 1 ? i + w : -1
                ])
                    if (j >= 0 && !seen[j] && !dark(j)) {
                        seen[j] = 1;
                        queue[tail++] = j;
                    }
            }
            if (
                !border &&
                tail > Math.max(30, n * 0.0003) &&
                inside((minX + maxX) / 2, (minY + maxY) / 2)
            )
                regions.push({ count: tail, minX, maxX, minY, maxY });
        }
        regions.sort((a, b) => b.count - a.count);
        regions.shift(); // largest enclosed region is the floor
        return regions
            .filter(
                (r) =>
                    r.count / ((r.maxX - r.minX + 1) * (r.maxY - r.minY + 1)) > 0.85 &&
                    r.maxX - r.minX > 3 &&
                    r.maxY - r.minY > 3
            )
            .map((r) => [
                [r.minX, r.minY],
                [r.maxX, r.minY],
                [r.maxX, r.maxY],
                [r.minX, r.maxY]
            ])
            .sort((a, b) => a[0][0] - b[0][0]);
    }
    root.RoomGeometry = { area, perimeter, bounds, valid, strips, detect, detectObjects };
    if (typeof module !== 'undefined') module.exports = root.RoomGeometry;
})(typeof globalThis !== 'undefined' ? globalThis : this);
