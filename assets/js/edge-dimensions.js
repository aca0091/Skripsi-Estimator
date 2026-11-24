(function (root) {
    'use strict';
    // Solve independent horizontal/vertical scales from measured edges.
    // Inconsistent dimensions are rejected instead of silently changing another measured edge.
    // Merapikan titik bentuk yang mendekati persegi panjang.
    function normalize(points) {
        const p = points.map((a) => a.slice());
        if (p.length !== 4) return p;
        const xs = p.map((a) => a[0]),
            ys = p.map((a) => a[1]),
            minX = Math.min(...xs),
            maxX = Math.max(...xs),
            minY = Math.min(...ys),
            maxY = Math.max(...ys),
            w = maxX - minX,
            h = maxY - minY;
        if (!(w > 0 && h > 0)) return p;
        // Remove sub-pixel corner noise only for a near-axis-aligned rectangle.
        if (
            !p.every(
                (a) =>
                    Math.min(a[0] - minX, maxX - a[0]) < w * 0.005 &&
                    Math.min(a[1] - minY, maxY - a[1]) < h * 0.005
            )
        )
            return p;
        const snapped = p.map((a) => [
            a[0] < (minX + maxX) / 2 ? minX : maxX,
            a[1] < (minY + maxY) / 2 ? minY : maxY
        ]);
        if (new Set(snapped.map((a) => a.join(','))).size !== 4) return p;
        return snapped;
    }
    // Membaca panjang sesuai satuan masukan.
    function parseLength(value, unit) {
        const match = String(value)
            .trim()
            .match(/^(\d+(?:[.,]\d+)?)\s*(cm|m|meter|sentimeter)?$/i);
        if (!match) return NaN;
        const suffix = (match[2] || unit).toLowerCase();
        return (
            Number(match[1].replace(',', '.')) /
            (['cm', 'sentimeter'].includes(suffix) ? 100 : 1)
        );
    }
    // Menghitung skala berdasarkan ukuran sisi.
    function calibrate(points, measurements, prior) {
        const equations = Object.entries(measurements).map(([key, length]) => {
            const i = Number(key),
                a = points[i],
                b = points[(i + 1) % points.length];
            if (!a || !b || !Number.isFinite(length) || length <= 0)
                throw Error('Ukuran sisi harus lebih dari nol.');
            return { x: (b[0] - a[0]) ** 2, y: (b[1] - a[1]) ** 2, z: length ** 2 };
        });
        if (!equations.length) return null;
        const size = Math.max(...equations.map((e) => e.x + e.y));
        if (!(size > 0)) throw Error('Garis terlalu pendek.');
        const rows = equations.map((e) => ({
            x: e.x / size,
            y: e.y / size,
            z: e.z / size
        }));
        let xx = 0,
            xy = 0,
            yy = 0,
            xz = 0,
            yz = 0;
        for (const e of rows) {
            xx += e.x * e.x;
            xy += e.x * e.y;
            yy += e.y * e.y;
            xz += e.x * e.z;
            yz += e.y * e.z;
        }
        const det = xx * yy - xy * xy;
        let sx2,
            sy2,
            complete = det > 1e-10 * (xx + yy) ** 2;
        if (complete) {
            sx2 = (xz * yy - yz * xy) / det;
            sy2 = (yz * xx - xz * xy) / det;
        } else {
            const e = rows[0];
            const ratio = prior && prior.x > 0 && prior.y > 0 ? prior.x / prior.y : 1;
            if (prior && prior.x > 0 && prior.y > 0 && e.y < 1e-12) {
                sy2 = prior.y ** 2;
                sx2 = e.z / e.x;
            } else if (prior && prior.x > 0 && prior.y > 0 && e.x < 1e-12) {
                sx2 = prior.x ** 2;
                sy2 = e.z / e.y;
            } else {
                sy2 = e.z / (e.x * ratio ** 2 + e.y);
                sx2 = sy2 * ratio ** 2;
            }
        }
        if (!(sx2 > 0 && sy2 > 0))
            throw Error(
                'Ukuran sisi tidak sesuai dengan bentuk. Periksa garis yang dipilih atau koreksi titik sudut.'
            );
        for (const e of rows)
            if (
                Math.abs(Math.sqrt(e.x * sx2 + e.y * sy2) - Math.sqrt(e.z)) >
                Math.max(1e-8 / Math.sqrt(size), Math.sqrt(e.z) * 1e-8)
            )
                throw Error(
                    'Ukuran bertentangan dengan sisi yang sudah diisi. Periksa ukurannya atau koreksi batas ruangan.'
                );
        return { x: Math.sqrt(sx2), y: Math.sqrt(sy2), complete };
    }
    // Fit individual wall lengths while keeping near-horizontal/vertical walls aligned.
    // The image supplies the initial shape, not a mandatory scale ratio.
    // Menyesuaikan bentuk dengan panjang sisi yang diisi.
    function fit(points, measurements, prior) {
        try {
            return calibrate(points, measurements, prior);
        } catch (error) {
            if (!Object.values(measurements).every((v) => Number.isFinite(v) && v > 0))
                throw error;
        }
        const entries = Object.entries(measurements).map(([i, length]) => [
            Number(i),
            length
        ]);
        const ratios = entries
            .map(
                ([i, l]) =>
                    l /
                    Math.hypot(
                        points[(i + 1) % points.length][0] - points[i][0],
                        points[(i + 1) % points.length][1] - points[i][1]
                    )
            )
            .sort((a, b) => a - b);
        const scale = ratios[Math.floor(ratios.length / 2)],
            p = points.map((a) => a.map((v) => v * scale));
        const axes = points.map((a, i) => {
            const b = points[(i + 1) % points.length],
                dx = Math.abs(b[0] - a[0]),
                dy = Math.abs(b[1] - a[1]);
            return dy < dx * 0.01 ? 1 : dx < dy * 0.01 ? 0 : -1;
        });
        for (let iteration = 0; iteration < 12000; iteration++) {
            for (let i = 0; i < p.length; i++)
                if (axes[i] >= 0) {
                    const a = p[i],
                        b = p[(i + 1) % p.length],
                        axis = axes[i],
                        middle = (a[axis] + b[axis]) / 2;
                    a[axis] = b[axis] = middle;
                }
            let maxError = 0;
            for (const [i, length] of entries) {
                const a = p[i],
                    b = p[(i + 1) % p.length],
                    dx = b[0] - a[0],
                    dy = b[1] - a[1],
                    d = Math.hypot(dx, dy);
                if (d < 1e-12) continue;
                const correction = (d - length) / (2 * d);
                a[0] += dx * correction;
                a[1] += dy * correction;
                b[0] -= dx * correction;
                b[1] -= dy * correction;
                maxError = Math.max(maxError, Math.abs(d - length));
            }
            const axisError = Math.max(
                ...axes.map((axis, i) =>
                    axis < 0 ? 0 : Math.abs(p[i][axis] - p[(i + 1) % p.length][axis])
                )
            );
            if (maxError < 1e-10 && axisError < 1e-10) break;
        }
        for (const [i, l] of entries)
            if (
                Math.abs(
                    Math.hypot(
                        p[i][0] - p[(i + 1) % p.length][0],
                        p[i][1] - p[(i + 1) % p.length][1]
                    ) - l
                ) > 1e-7
            )
                throw Error(
                    'Ukuran sisi saling bertentangan dan belum dapat membentuk ruangan tertutup. Periksa ukuran sisi lainnya.'
                );
        if (
            axes.some(
                (axis, i) =>
                    axis >= 0 && Math.abs(p[i][axis] - p[(i + 1) % p.length][axis]) > 1e-7
            )
        )
            throw Error(
                'Ukuran sisi saling bertentangan. Sesuaikan sisi lainnya agar ruangan dapat ditutup.'
            );
        const directions = entries.map(([i]) => {
            const a = points[i],
                b = points[(i + 1) % points.length],
                d = Math.hypot(b[0] - a[0], b[1] - a[1]);
            return [(b[0] - a[0]) / d, (b[1] - a[1]) / d];
        });
        const complete = directions.some((a) =>
            directions.some((b) => Math.abs(a[0] * b[1] - a[1] * b[0]) > 0.05)
        );
        return { x: 1, y: 1, points: p, complete };
    }
    // Mencari sisi terdekat dari posisi penunjuk.
    function nearest(points, p, tolerance) {
        let index = -1,
            best = tolerance;
        points.forEach((a, i) => {
            const b = points[(i + 1) % points.length],
                dx = b[0] - a[0],
                dy = b[1] - a[1],
                den = dx * dx + dy * dy;
            if (!den) return;
            const t = Math.max(
                0,
                Math.min(1, ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / den)
            );
            const d = Math.hypot(p[0] - a[0] - t * dx, p[1] - a[1] - t * dy);
            if (d < best) {
                index = i;
                best = d;
            }
        });
        return index;
    }
    root.EdgeDimensions = { calibrate, fit, nearest, normalize, parseLength };
    if (typeof module !== 'undefined') module.exports = root.EdgeDimensions;
})(typeof globalThis !== 'undefined' ? globalThis : this);
