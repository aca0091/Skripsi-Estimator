let layoutObjects = [],
    selectedObject = -1,
    selectedObjectEdge = -1,
    objectDrawing = false,
    objectDraft = [];
let measuredVertexHeights = {},
    roomPoints = [],
    calibrationBounds = null,
    imageTransform = null,
    detectedImage = null,
    manualTracing = false,
    draggingVertex = -1;
// Menampilkan status pengolahan denah.
function layoutStatus(message) {
    document.getElementById('layoutStatus').textContent = message;
}

// Mengubah titik denah menjadi koordinat meter.
function realRoomPoints() {
    if (edgeCalibration && edgeReferencePoints) {
        const physical = edgeCalibration.points || edgeReferencePoints;
        const b = RoomGeometry.bounds(physical);
        return physical.map((p) => [
            Number(((p[0] - b.minX) * edgeCalibration.x).toFixed(12)),
            Number(((p[1] - b.minY) * edgeCalibration.y).toFixed(12))
        ]);
    }
    const factor = document.getElementById('dimensionUnit').value === 'cm' ? 0.01 : 1;
    const P = Number(document.getElementById('dimPanjang').value) * factor,
        L = Number(document.getElementById('dimLebar').value) * factor;
    if (!calibrationBounds || !(P > 0 && L > 0)) return [];
    const b = calibrationBounds;
    return roomPoints.map((p, index) => [
        ((p[0] - b.minX) * P) / (b.maxX - b.minX),
        (measuredVertexHeights[index] ?? (p[1] - b.minY) / (b.maxY - b.minY)) * L
    ]);
}

// Menerapkan batas ruangan dan memperbarui estimasi.
function acceptRoomPoints(points) {
    invalidateEdgeMeasurements();
    measuredVertexHeights = {};
    roomPoints = points;
    calibrationBounds = points.length >= 3 ? RoomGeometry.bounds(points) : null;
    document.getElementById('confirmScale').checked = false;
    renderVertexInputs();
    renderMainCanvas();
    runSimulasiDanHitung();
}

// Menampilkan isian koordinat sudut ruangan.
function renderVertexInputs() {
    refreshEdgeOptions();
    const box = document.getElementById('vertexInputs');
    box.replaceChildren();
    realRoomPoints().forEach((point, index) => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;gap:8px;align-items:center;margin:6px 0';
        const label = document.createElement('span');
        label.textContent = `Titik ${index + 1}`;
        row.append(label);
        point.forEach((value, axis) => {
            const input = document.createElement('input');
            input.type = 'number';
            input.step = '.001';
            input.min = '0';
            input.value = value.toFixed(3);
            input.className = 'form-ctrl';
            input.setAttribute(
                'aria-label',
                `Titik ${index + 1} ${axis === 0 ? 'X' : 'Y'} dalam meter`
            );
            input.onchange = () => {
                const number = Number(input.value);
                if (!Number.isFinite(number) || number < 0) return;
                invalidateEdgeMeasurements();
                const size =
                    Number(
                        document.getElementById(axis === 0 ? 'dimPanjang' : 'dimLebar')
                            .value
                    ) *
                    (document.getElementById('dimensionUnit').value === 'cm' ? 0.01 : 1);
                const b = calibrationBounds;
                if (axis === 1) delete measuredVertexHeights[index];
                roomPoints[index][axis] =
                    (axis === 0 ? b.minX : b.minY) +
                    (number / size) * (axis === 0 ? b.maxX - b.minX : b.maxY - b.minY);
                document.getElementById('confirmScale').checked = false;
                renderMainCanvas();
                runSimulasiDanHitung();
            };
            row.append(input);
        });
        box.append(row);
    });
}

// Mendeteksi batas ruangan dan objek dari denah.
function prosesCitraDigitalNyata(force = false) {
    if (!uploadedImageObj) return;
    if (detectedImage === uploadedImageObj && !force) {
        renderMainCanvas();
        return;
    }
    detectedImage = uploadedImageObj;
    const c = document.createElement('canvas'),
        scale = Math.min(
            1,
            1200 / uploadedImageObj.width,
            1200 / uploadedImageObj.height
        );
    c.width = Math.round(uploadedImageObj.width * scale);
    c.height = Math.round(uploadedImageObj.height * scale);
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(uploadedImageObj, 0, 0, c.width, c.height);
    const pixels = ctx.getImageData(0, 0, c.width, c.height).data;
    const outline = RoomGeometry.detect(pixels, c.width, c.height);
    layoutObjects = RoomGeometry.detectObjects(pixels, c.width, c.height, outline).map(
        (points) => ({
            points: points.map((p) => p.map((v) => v / scale)),
            width: 0,
            height: 0
        })
    );
    selectedObject = -1;
    selectedObjectEdge = -1;
    objectDrawing = false;
    objectDraft = [];
    const points = outline.map((p) => [p[0] / scale, p[1] / scale]);
    renderObjectInputs();
    manualTracing = false;
    document.getElementById('dimObjek').value = '0';
    acceptRoomPoints(points);
    layoutStatus(
        points.length
            ? `${points.length} titik batas ditemukan. Pilih garis untuk mengisi ukuran.`
            : 'Batas tertutup belum ditemukan. Klik “Gambar batas manual”, klik sudut berurutan, lalu “Selesai menggambar”.'
    );
}

// Menggambar denah dan penanda pada kanvas.
function renderMainCanvas() {
    if (!mainCtx) return;
    mainCtx.clearRect(0, 0, mainCanvas.width, mainCanvas.height);
    mainCtx.fillStyle = '#f8fafc';
    mainCtx.fillRect(0, 0, mainCanvas.width, mainCanvas.height);
    if (!uploadedImageObj) {
        mainCtx.fillStyle = '#475569';
        mainCtx.font = '16px Arial';
        mainCtx.fillText('Unggah denah untuk menentukan batas ruangan.', 30, 80);
        return;
    }
    const scale = Math.min(
        (mainCanvas.width - 40) / uploadedImageObj.width,
        (mainCanvas.height - 40) / uploadedImageObj.height
    );
    imageTransform = {
        scale,
        x: (mainCanvas.width - uploadedImageObj.width * scale) / 2,
        y: (mainCanvas.height - uploadedImageObj.height * scale) / 2
    };
    const t = imageTransform;
    mainCtx.drawImage(
        uploadedImageObj,
        t.x,
        t.y,
        uploadedImageObj.width * scale,
        uploadedImageObj.height * scale
    );
    if (!roomPoints.length) return;
    mainCtx.beginPath();
    roomPoints.forEach((p, i) =>
        mainCtx[i ? 'lineTo' : 'moveTo'](t.x + p[0] * scale, t.y + p[1] * scale)
    );
    if (!manualTracing) {
        mainCtx.closePath();
        mainCtx.fillStyle = 'rgba(16,185,129,.12)';
        mainCtx.fill();
    }
    mainCtx.strokeStyle = '#059669';
    mainCtx.lineWidth = 2;
    mainCtx.stroke();
    drawEdgeMeasurements();
    drawLayoutObjects();
    roomPoints.forEach((p, i) => {
        const x = t.x + p[0] * scale,
            y = t.y + p[1] * scale;
        mainCtx.beginPath();
        mainCtx.arc(x, y, 5, 0, Math.PI * 2);
        mainCtx.fillStyle = '#f59e0b';
        mainCtx.fill();
        mainCtx.fillStyle = '#0f172a';
        mainCtx.font = 'bold 13px Arial';
        mainCtx.fillText(String(i + 1), x + 8, y - 8);
    });
}

// Memulai penggambaran batas ruangan manual.
function startManualOutline() {
    objectDrawing = false;
    objectDraft = [];
    invalidateEdgeMeasurements();
    manualTracing = true;
    measuredVertexHeights = {};
    roomPoints = [];
    calibrationBounds = null;
    document.getElementById('confirmScale').checked = false;
    layoutStatus(
        'Klik setiap sudut dinding secara berurutan. Jangan ikuti tulisan atau garis ukuran di dalam ruangan.'
    );
    renderMainCanvas();
    runSimulasiDanHitung();
}

// Memvalidasi dan menyelesaikan batas manual.
function finishManualOutline() {
    if (!RoomGeometry.valid(roomPoints)) {
        layoutStatus(
            'Butuh minimal 3 titik dengan batas yang tidak saling bersilangan. Gambar ulang jika perlu.'
        );
        return;
    }
    manualTracing = false;
    acceptRoomPoints(roomPoints);
    layoutStatus('Batas manual siap. Masukkan ukuran nyata dan periksa koordinat sudut.');
}

// Menghapus titik batas terakhir.
function resetLastPoint() {
    if (manualTracing) {
        roomPoints.pop();
        renderMainCanvas();
    }
}

// Memperbarui nama objek pengurang luas.
function updateNamaObjek() {
    customNamaObjek = document.getElementById('txtNamaObjek').value.trim() || 'Objek';
    document.getElementById('post_nama_objek').value = customNamaObjek;
}

// Menggambar pratinjau jalur pemasangan karpet.
function drawRoomPreview(canvas, ctx, axis) {
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const points = realRoomPoints();
    if (!RoomGeometry.valid(points)) return;
    const b = RoomGeometry.bounds(points),
        scale = Math.min(
            (canvas.width - 30) / (b.maxX - b.minX),
            (canvas.height - 30) / (b.maxY - b.minY)
        );
    const x = (canvas.width - (b.maxX - b.minX) * scale) / 2,
        y = (canvas.height - (b.maxY - b.minY) * scale) / 2;
    ctx.save();
    ctx.beginPath();
    points.forEach((p, i) =>
        ctx[i ? 'lineTo' : 'moveTo'](
            x + (p[0] - b.minX) * scale,
            y + (p[1] - b.minY) * scale
        )
    );
    ctx.closePath();
    ctx.fillStyle = 'rgba(16,185,129,.15)';
    ctx.fill();
    ctx.strokeStyle = '#059669';
    ctx.lineWidth = 2;
    ctx.stroke();
    ctx.clip();
    const select = document.getElementById('selectBahanKarpet'),
        width = Number(select?.selectedOptions[0]?.dataset.lebar);
    if (width > 0) {
        ctx.strokeStyle = '#d97706';
        ctx.setLineDash([4, 3]);
        const start = axis === 0 ? b.minX : b.minY,
            end = axis === 0 ? b.maxX : b.maxY;
        for (let v = start + width, n = 0; v < end && n < 1000; v += width, n++) {
            ctx.beginPath();
            if (axis === 0) {
                ctx.moveTo(x + (v - b.minX) * scale, y);
                ctx.lineTo(x + (v - b.minX) * scale, canvas.height);
            } else {
                ctx.moveTo(x, y + (v - b.minY) * scale);
                ctx.lineTo(canvas.width, y + (v - b.minY) * scale);
            }
            ctx.stroke();
        }
    }
    ctx.restore();
}

// Menampilkan pratinjau pemasangan vertikal.
function renderVisualArahVertikal() {
    drawRoomPreview(cvVert, ctxVert, 0);
}

// Menampilkan pratinjau pemasangan horizontal.
function renderVisualArahHorizontal() {
    drawRoomPreview(cvHoriz, ctxHoriz, 1);
}
mainCanvas.style.touchAction = 'none';
// Mengubah posisi penunjuk menjadi koordinat gambar.
function imagePointer(event) {
    const r = mainCanvas.getBoundingClientRect(),
        t = imageTransform;
    return [
        (((event.clientX - r.left) * mainCanvas.width) / r.width - t.x) / t.scale,
        (((event.clientY - r.top) * mainCanvas.height) / r.height - t.y) / t.scale
    ];
}
mainCanvas.addEventListener('pointerdown', (event) => {
    if (!imageTransform) return;
    const p = imagePointer(event);
    if (
        p[0] < 0 ||
        p[1] < 0 ||
        p[0] > uploadedImageObj.width ||
        p[1] > uploadedImageObj.height
    )
        return;
    if (objectDrawing) {
        addObjectPoint(p);
        return;
    }
    let hit = null,
        best = 14 / imageTransform.scale;
    layoutObjects.forEach((o, i) => {
        const edge = EdgeDimensions.nearest(o.points, p, best);
        if (edge >= 0) {
            const a = o.points[edge],
                b = o.points[(edge + 1) % o.points.length],
                dx = b[0] - a[0],
                dy = b[1] - a[1],
                t = Math.max(
                    0,
                    Math.min(
                        1,
                        ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / (dx * dx + dy * dy)
                    )
                );
            best = Math.hypot(p[0] - a[0] - t * dx, p[1] - a[1] - t * dy);
            hit = [i, edge];
        }
    });
    if (hit && !manualTracing) {
        selectObjectEdge(...hit);
        return;
    }
    if (manualTracing) {
        if (roomPoints.length < 100) roomPoints.push(p);
        renderMainCanvas();
        return;
    }
    draggingVertex = roomPoints.findIndex(
        (a) => Math.hypot(a[0] - p[0], a[1] - p[1]) * imageTransform.scale < 14
    );
    if (draggingVertex >= 0) mainCanvas.setPointerCapture(event.pointerId);
    else {
        const edge = EdgeDimensions.nearest(roomPoints, p, 14 / imageTransform.scale);
        if (edge >= 0) selectRoomEdge(edge);
    }
});
mainCanvas.addEventListener('pointermove', (event) => {
    if (draggingVertex < 0) return;
    invalidateEdgeMeasurements();
    const p = imagePointer(event);
    delete measuredVertexHeights[draggingVertex];
    roomPoints[draggingVertex] = [
        Math.max(0, Math.min(uploadedImageObj.width, p[0])),
        Math.max(0, Math.min(uploadedImageObj.height, p[1]))
    ];
    document.getElementById('confirmScale').checked = false;
    renderMainCanvas();
    renderVertexInputs();
    runSimulasiDanHitung();
});
for (const eventName of ['pointerup', 'pointercancel'])
    mainCanvas.addEventListener(eventName, () => {
        draggingVertex = -1;
    });
for (const id of ['dimPanjang', 'dimLebar'])
    document.getElementById(id).addEventListener('input', () => {
        invalidateEdgeMeasurements();
        document.getElementById('confirmScale').checked = false;
        renderVertexInputs();
        runSimulasiDanHitung();
    });

let previousDimensionUnit = 'm';
// Mengubah satuan ukuran masukan.
function changeDimensionUnit(unit) {
    const factor = previousDimensionUnit === unit ? 1 : unit === 'cm' ? 100 : 0.01;
    for (const id of ['dimPanjang', 'dimLebar']) {
        const input = document.getElementById(id);
        if (input.value !== '')
            input.value = Number((Number(input.value) * factor).toFixed(6));
    }
    previousDimensionUnit = unit;
    document.getElementById('confirmScale').checked = false;
    renderVertexInputs();
    runSimulasiDanHitung();
}

// Memeriksa kesalahan ukuran ruangan.
function measurementError() {
    if (!isImageReady) return 'Unggah gambar ruangan terlebih dahulu.';
    if (edgeCalibration && !edgeCalibration.complete && !edgeHadPrior)
        return 'Tambahkan ukuran sisi dengan arah berbeda agar skala horizontal dan vertikal diketahui.';
    if (objectDrawing) return 'Selesaikan atau batalkan gambar objek.';
    if (layoutObjects.some((o) => !(o.width > 0 && o.height > 0)))
        return 'Isi lebar dan tinggi setiap kandidat objek, atau hapus kandidat yang bukan objek.';
    if (manualTracing) return 'Klik Selesai menggambar untuk menutup batas ruangan.';
    if (!RoomGeometry.valid(roomPoints))
        return 'Periksa batas ruangan: minimal tiga sudut dan tidak saling bersilangan.';
    const P = Number(document.getElementById('dimPanjang').value),
        L = Number(document.getElementById('dimLebar').value);
    if (!(P > 0 && L > 0 && Number.isFinite(P) && Number.isFinite(L)))
        return 'Isi panjang dan lebar maksimum, lalu pilih satuannya (cm atau m).';
    const points = realRoomPoints();
    if (
        !RoomGeometry.valid(points) ||
        points.some((p) => p.some((v) => v < 0 || v > 10000))
    )
        return 'Periksa koordinat sudut dan ukuran ruangan.';
    const objectArea = Number(document.getElementById('dimObjek').value);
    if (
        !Number.isFinite(objectArea) ||
        objectArea < 0 ||
        objectArea >= RoomGeometry.area(points)
    )
        return 'Luas objek harus nol atau lebih, dan lebih kecil dari luas ruangan.';
    return '';
}

// Memperbarui status validasi ukuran.
function updateMeasurementStatus() {
    const error = measurementError();
    document.getElementById('measurementStatus').textContent = calculationLoading
        ? ''
        : isImageReady
          ? error
          : '';
}

// Menghitung ukuran ruangan dari batas denah.
function calculateRoomMeasurements() {
    const error = measurementError();
    document.getElementById('confirmScale').checked = !error;
    runSimulasiDanHitung();
    if (error) document.getElementById('measurementStatus').textContent = error;
}

// Menghitung luas objek di dalam ruangan.
function calculateObjectArea() {
    if (layoutObjects.length) {
        document.getElementById('measurementStatus').textContent =
            'Isi ukuran pada daftar objek terdeteksi agar luas tidak dihitung dua kali.';
        return;
    }
    const length = Number(document.getElementById('objectLength').value),
        width = Number(document.getElementById('objectWidth').value),
        count = Number(document.getElementById('objectCount').value);
    if (!(
        length > 0 &&
        width > 0 &&
        Number.isFinite(length) &&
        Number.isFinite(width) &&
        Number.isInteger(count) &&
        count > 0
    )) {
        document.getElementById('measurementStatus').textContent =
            'Isi panjang dan lebar objek lebih dari nol serta jumlah objek bulat positif.';
        return;
    }
    document.getElementById('dimObjek').value = Number(
        (length * width * count).toFixed(6)
    );
    document.getElementById('confirmScale').checked = false;
    runSimulasiDanHitung();
}
document.getElementById('dimObjek').addEventListener('input', () => {
    document.getElementById('confirmScale').checked = false;
    updateMeasurementStatus();
});

let calculationLoading = false;
// Mengatur indikator proses perhitungan.
function setCalculationLoading(busy, message, error = false) {
    calculationLoading = busy;
    const status = document.getElementById('calculationStatus');
    status.setAttribute('data-loading', String(busy));
    status.setAttribute('data-state', error ? 'error' : busy ? 'loading' : 'ready');
    document.getElementById('dimensionReadStatus').textContent = message;
    document.getElementById('measurementSummary').setAttribute('aria-busy', String(busy));
    document.getElementById('nextCarpetButton').disabled = busy;
}

let layoutUploadVersion = 0;
// Mengosongkan denah dan hasil estimasi.
function resetLayoutEstimate(clearFile = true) {
    // Invalidate all pending image/OCR callbacks before clearing the current estimate.
    if (typeof clearAutomaticDimensions === 'function') clearAutomaticDimensions();
    invalidateEdgeMeasurements();
    layoutUploadVersion++;
    uploadedImageObj = null;
    isImageReady = false;
    detectedImage = null;
    imageTransform = null;
    layoutObjects = [];
    selectedObject = -1;
    selectedObjectEdge = -1;
    objectDrawing = false;
    objectDraft = [];
    renderObjectInputs();
    document.getElementById('objectDrawStatus').textContent = '';
    roomPoints = [];
    calibrationBounds = null;
    measuredVertexHeights = {};
    manualTracing = false;
    draggingVertex = -1;
    for (const id of ['dimPanjang', 'dimLebar', 'objectLength', 'objectWidth'])
        document.getElementById(id).value = '';
    document.getElementById('dimObjek').value = '0';
    document.getElementById('objectCount').value = '1';
    document.getElementById('txtNamaObjek').value = 'Objek';
    customNamaObjek = 'Objek';
    document.getElementById('post_nama_objek').value = 'Objek';
    document.getElementById('dimensionUnit').value = 'm';
    previousDimensionUnit = 'm';
    document.getElementById('confirmScale').checked = false;
    if (clearFile) document.getElementById('inputLayoutFile').value = '';
    document.getElementById('txtUploadNama').textContent =
        'Klik untuk memilih foto denah layout';
    setCalculationLoading(false, 'Unggah gambar, lalu pilih garis untuk mengisi ukuran.');
    document.getElementById('calculationStatus').setAttribute('data-state', 'idle');
    layoutStatus('');
    renderVertexInputs();
    renderMainCanvas();
    setMetodePemasangan('Disambung');
    pilihArahPemasangan('Vertikal');
    syncFinalTable();
}

// Browser Back may restore a completed form from the page cache.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        resetLayoutEstimate();
        goToStep(1);
    }
});

let selectedRoomEdge = -1,
    edgeMeasurements = {},
    edgeReferencePoints = null,
    edgeCalibration = null,
    edgeHadPrior = false;
// Menghapus kalibrasi sisi yang sudah tidak berlaku.
function invalidateEdgeMeasurements() {
    selectedObject = -1;
    selectedObjectEdge = -1;
    selectedRoomEdge = -1;
    edgeMeasurements = {};
    edgeReferencePoints = null;
    edgeCalibration = null;
    edgeHadPrior = false;
    const status = document.getElementById('edgeMeasurementStatus');
    if (status)
        status.textContent = 'Pilih garis hijau atau pilih pasangan titik di atas.';
    const input = document.getElementById('edgeLength');
    if (input) input.value = '';
}

// Memperbarui pilihan sisi ruangan.
function refreshEdgeOptions() {
    const select = document.getElementById('edgeSelect');
    if (!select) return;
    select.replaceChildren();
    const placeholder = document.createElement('option');
    placeholder.value = '-1';
    placeholder.textContent = 'Pilih garis';
    select.append(placeholder);
    if (!manualTracing)
        roomPoints.forEach((p, i) => {
            const option = document.createElement('option');
            option.value = String(i);
            option.textContent = `Sisi ${i + 1}: titik ${i + 1} – ${((i + 1) % roomPoints.length) + 1}`;
            select.append(option);
        });
    select.value = String(selectedRoomEdge);
}

// Memilih sisi ruangan untuk diukur.
function selectRoomEdge(index) {
    selectedObject = -1;
    selectedObjectEdge = -1;
    if (
        !Number.isInteger(index) ||
        index < 0 ||
        index >= roomPoints.length ||
        manualTracing
    )
        return;
    selectedRoomEdge = index;
    document.getElementById('edgeSelect').value = String(index);
    const points = realRoomPoints(),
        end = (index + 1) % roomPoints.length;
    const length =
        edgeMeasurements[index] ??
        (RoomGeometry.valid(points)
            ? Math.hypot(
                  points[end][0] - points[index][0],
                  points[end][1] - points[index][1]
              )
            : null);
    const unit = document.getElementById('edgeUnit').value;
    document.getElementById('edgeLength').value =
        length === null ? '' : Number((length * (unit === 'cm' ? 100 : 1)).toFixed(3));
    document.getElementById('edgeMeasurementStatus').textContent =
        `Sisi ${index + 1} dipilih: titik ${index + 1} ke titik ${end + 1}.`;
    renderMainCanvas();
}

// Menerapkan ukuran pada sisi ruangan.
function applyRoomEdgeMeasurement() {
    if (selectedObject >= 0) {
        applyObjectEdgeMeasurement();
        return;
    }
    const status = document.getElementById('edgeMeasurementStatus');
    if (selectedRoomEdge < 0) {
        status.textContent = 'Pilih garis pada gambar terlebih dahulu.';
        return;
    }
    const length = EdgeDimensions.parseLength(
        document.getElementById('edgeLength').value,
        document.getElementById('edgeUnit').value
    );
    if (!(length > 0 && length <= 10000 && Number.isFinite(length))) {
        status.textContent = 'Masukkan panjang sisi yang valid.';
        return;
    }
    const current = realRoomPoints();
    const reference =
        edgeReferencePoints ||
        EdgeDimensions.normalize(RoomGeometry.valid(current) ? current : roomPoints);
    const hadPrior = edgeReferencePoints ? edgeHadPrior : RoomGeometry.valid(current);
    const measurements = { ...edgeMeasurements, [selectedRoomEdge]: length };
    try {
        const scale = EdgeDimensions.fit(
            reference,
            measurements,
            hadPrior ? { x: 1, y: 1 } : null
        );
        if (scale.points && !RoomGeometry.valid(scale.points))
            throw Error(
                'Ukuran membuat garis ruangan berpotongan. Periksa ukuran sisi lainnya.'
            );
        const b = RoomGeometry.bounds(scale.points || reference),
            width = (b.maxX - b.minX) * scale.x,
            height = (b.maxY - b.minY) * scale.y;
        if (!(width > 0 && height > 0 && width <= 10000 && height <= 10000))
            throw Error('Ukuran ruangan di luar batas.');
        edgeReferencePoints = reference.map((p) => p.slice());
        edgeHadPrior = hadPrior;
        edgeMeasurements = measurements;
        edgeCalibration = scale;
        document.getElementById('dimensionUnit').value = 'm';
        previousDimensionUnit = 'm';
        document.getElementById('dimPanjang').value = Number(width.toFixed(6));
        document.getElementById('dimLebar').value = Number(height.toFixed(6));
        setCalculationLoading(
            false,
            scale.complete || hadPrior
                ? 'Ukuran sisi manual diterapkan.'
                : 'Isi satu sisi lain dengan arah berbeda.'
        );
        document.getElementById('confirmScale').checked = false;
        renderVertexInputs();
        renderMainCanvas();
        runSimulasiDanHitung();
        status.textContent =
            `Sisi ${selectedRoomEdge + 1} = ${length.toLocaleString('id-ID')} m. ` +
            (scale.complete || hadPrior
                ? 'Luas dan keliling diperbarui.'
                : 'Ukuran sisi lainnya masih perkiraan sampai skala arah lainnya diisi.');
    } catch (error) {
        status.textContent = error.message;
    }
}

// Menghapus seluruh ukuran sisi ruangan.
function clearRoomEdgeMeasurements() {
    invalidateEdgeMeasurements();
    document.getElementById('dimPanjang').value = '';
    document.getElementById('dimLebar').value = '';
    setCalculationLoading(false, 'Pilih garis untuk mengisi ukuran.');
    renderVertexInputs();
    renderMainCanvas();
    runSimulasiDanHitung();
}

// Menggambar label ukuran sisi ruangan.
function drawEdgeMeasurements() {
    if (manualTracing || !imageTransform) return;
    const points = realRoomPoints(),
        valid = RoomGeometry.valid(points),
        t = imageTransform;
    roomPoints.forEach((a, i) => {
        const j = (i + 1) % roomPoints.length,
            b = roomPoints[j],
            x1 = t.x + a[0] * t.scale,
            y1 = t.y + a[1] * t.scale,
            x2 = t.x + b[0] * t.scale,
            y2 = t.y + b[1] * t.scale;
        if (i === selectedRoomEdge) {
            mainCtx.beginPath();
            mainCtx.moveTo(x1, y1);
            mainCtx.lineTo(x2, y2);
            mainCtx.strokeStyle = '#2563eb';
            mainCtx.lineWidth = 5;
            mainCtx.stroke();
        }
        if (i !== selectedRoomEdge) return; // Keep the outline readable; show the label for the chosen side.
        const measured = Object.prototype.hasOwnProperty.call(edgeMeasurements, i);
        const length = measured
            ? edgeMeasurements[i]
            : valid
              ? Math.hypot(points[i][0] - points[j][0], points[i][1] - points[j][1])
              : null;
        const text =
            `${i + 1}–${j + 1}` +
            (length === null
                ? ''
                : ` · ${length.toLocaleString('id-ID', { maximumFractionDigits: 3 })} m`) +
            (measured ? ' ✓' : '');
        const x = (x1 + x2) / 2,
            y = (y1 + y2) / 2;
        mainCtx.font = 'bold 13px Arial';
        const width = mainCtx.measureText(text).width + 14;
        mainCtx.fillStyle = i === selectedRoomEdge ? '#2563eb' : '#ffffff';
        mainCtx.fillRect(x - width / 2, y - 13, width, 24);
        mainCtx.fillStyle = i === selectedRoomEdge ? '#ffffff' : '#0f172a';
        mainCtx.fillText(text, x - width / 2 + 7, y + 4);
    });
}
document.getElementById('edgeUnit').addEventListener('change', () => {
    if (selectedObject >= 0) selectObjectEdge(selectedObject, selectedObjectEdge);
    else if (selectedRoomEdge >= 0) selectRoomEdge(selectedRoomEdge);
});

// Menampilkan isian ukuran objek.
function renderObjectInputs() {
    const box = document.getElementById('detectedObjects');
    if (!box) return;
    box.replaceChildren();
    const note = document.createElement('p');
    note.textContent = layoutObjects.length
        ? `${layoutObjects.length} objek — merah: belum diukur.`
        : 'Belum ada objek.';
    box.append(note);
    layoutObjects.forEach((o, i) => {
        const row = document.createElement('div');
        row.style.cssText =
            'display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0';
        const label = document.createElement('strong');
        label.textContent = `Objek ${i + 1}`;
        row.append(label);
        for (const key of ['width', 'height']) {
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0.001';
            input.step = '0.001';
            input.className = 'form-ctrl';
            input.style.width = '170px';
            input.placeholder =
                key === 'width' ? 'Lebar total objek (m)' : 'Tinggi total objek (m)';
            input.setAttribute('aria-label', `Objek ${i + 1} ${input.placeholder}`);
            input.value = o[key] || '';
            input.oninput = () => {
                o[key] = Number(input.value);
                o.measurements = {};
                syncObjectArea();
            };
            row.append(input);
        }
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn-wizard';
        remove.textContent = 'Hapus kandidat';
        remove.onclick = () => {
            layoutObjects.splice(i, 1);
            selectedObject = -1;
            selectedObjectEdge = -1;
            renderObjectInputs();
            syncObjectArea();
        };
        row.append(remove);
        box.append(row);
    });
    document.getElementById('dimObjek').readOnly = layoutObjects.length > 0;
}

// Menyelaraskan luas objek dengan estimasi.
function syncObjectArea() {
    document.getElementById('dimObjek').value = Number(
        layoutObjects
            .reduce(
                (sum, o) =>
                    sum +
                    (o.width > 0 && o.height > 0
                        ? (RoomGeometry.area(o.points) * o.width * o.height) /
                          ((RoomGeometry.bounds(o.points).maxX -
                              RoomGeometry.bounds(o.points).minX) *
                              (RoomGeometry.bounds(o.points).maxY -
                                  RoomGeometry.bounds(o.points).minY))
                        : 0),
                0
            )
            .toFixed(6)
    );
    document.getElementById('confirmScale').checked = false;
    runSimulasiDanHitung();
}

// Memilih sisi objek untuk diukur.
function selectObjectEdge(index, edge) {
    selectedObject = index;
    selectedObjectEdge = edge;
    selectedRoomEdge = -1;
    document.getElementById('edgeSelect').value = '-1';
    const o = layoutObjects[index],
        b = RoomGeometry.bounds(o.points),
        a = o.points[edge],
        end = o.points[(edge + 1) % o.points.length];
    const value =
        o.measurements?.[edge] ||
        (o.width > 0 && o.height > 0
            ? Math.hypot(
                  ((end[0] - a[0]) * o.width) / (b.maxX - b.minX),
                  ((end[1] - a[1]) * o.height) / (b.maxY - b.minY)
              )
            : 0);
    document.getElementById('edgeLength').value = value
        ? Number(
              (
                  value * (document.getElementById('edgeUnit').value === 'cm' ? 100 : 1)
              ).toFixed(3)
          )
        : '';
    document.getElementById('edgeMeasurementStatus').textContent =
        `Objek ${index + 1}, sisi ${edge + 1}: masukkan panjang sisi nyata. Ukur dua arah berbeda untuk menentukan luas.`;
    renderMainCanvas();
}

// Menerapkan ukuran pada sisi objek.
function applyObjectEdgeMeasurement() {
    const value = EdgeDimensions.parseLength(
        document.getElementById('edgeLength').value,
        document.getElementById('edgeUnit').value
    );
    if (!(value > 0 && value <= 10000)) {
        document.getElementById('edgeMeasurementStatus').textContent =
            'Masukkan ukuran objek yang valid.';
        return;
    }
    const o = layoutObjects[selectedObject],
        b = RoomGeometry.bounds(o.points);
    const measurements = { ...o.measurements, [selectedObjectEdge]: value };
    const prior =
        o.width > 0 && o.height > 0
            ? { x: o.width / (b.maxX - b.minX), y: o.height / (b.maxY - b.minY) }
            : null;
    try {
        const scale = EdgeDimensions.calibrate(o.points, measurements, prior);
        o.measurements = measurements;
        if (scale.complete || prior) {
            o.width = (b.maxX - b.minX) * scale.x;
            o.height = (b.maxY - b.minY) * scale.y;
        }
        renderObjectInputs();
        syncObjectArea();
        document.getElementById('edgeMeasurementStatus').textContent =
            scale.complete || prior
                ? 'Ukuran dan luas objek diperbarui sesuai bentuknya.'
                : 'Ukuran sisi disimpan. Isi sisi dengan arah berbeda untuk menentukan luas.';
    } catch (error) {
        document.getElementById('edgeMeasurementStatus').textContent = error.message;
    }
}

// Menampilkan status penggambaran objek.
function objectDrawStatus(message) {
    layoutStatus(message);
    document.getElementById('objectDrawStatus').textContent = message;
}

// Memulai penggambaran batas objek.
function startObjectPolygon() {
    if (!uploadedImageObj || !RoomGeometry.valid(roomPoints)) {
        objectDrawStatus('Unggah denah dan selesaikan batas ruangan terlebih dahulu.');
        return;
    }
    manualTracing = false;
    draggingVertex = -1;
    selectedObject = -1;
    selectedObjectEdge = -1;
    objectDrawing = true;
    objectDraft = [];
    document.getElementById('confirmScale').checked = false;
    objectDrawStatus(
        'Klik minimal 3 sudut secara berurutan, lalu klik Selesai gambar objek atau kembali ke titik pertama.'
    );
    renderMainCanvas();
}

// Menambahkan titik sudut objek.
function addObjectPoint(p) {
    if (
        objectDraft.length >= 3 &&
        Math.hypot(p[0] - objectDraft[0][0], p[1] - objectDraft[0][1]) *
            imageTransform.scale <
            10
    ) {
        finishObjectPolygon();
        return;
    }
    if (objectDraft.length >= 100) {
        objectDrawStatus('Maksimal 100 sudut per objek. Selesaikan gambar objek.');
        return;
    }
    if (objectDraft.some((a) => Math.hypot(a[0] - p[0], a[1] - p[1]) < 1)) {
        objectDrawStatus('Pilih sudut yang berbeda.');
        return;
    }
    objectDraft.push(p);
    objectDrawStatus(
        `${objectDraft.length} sudut ditambahkan. Lanjutkan klik sudut, lalu Selesai gambar objek.`
    );
    renderMainCanvas();
}

// Menghapus titik objek terakhir.
function undoObjectPoint() {
    if (objectDrawing) {
        objectDraft.pop();
        objectDrawStatus(`${objectDraft.length} sudut tersisa.`);
        renderMainCanvas();
    }
}

// Menyelesaikan dan memvalidasi batas objek.
function finishObjectPolygon() {
    if (!objectDrawing) return;
    if (!RoomGeometry.valid(objectDraft)) {
        objectDrawStatus(
            'Butuh minimal 3 sudut dan garis tidak boleh saling bersilangan. Batalkan titik terakhir untuk memperbaiki.'
        );
        return;
    }
    layoutObjects.push({
        points: objectDraft.map((p) => p.slice()),
        width: 0,
        height: 0
    });
    objectDrawing = false;
    objectDraft = [];
    renderObjectInputs();
    syncObjectArea();
    selectObjectEdge(layoutObjects.length - 1, 0);
    objectDrawStatus(
        'Bentuk objek sudah tertutup. Isi lebar dan tinggi total, atau ukur sisi dalam dua arah berbeda.'
    );
}

// Membatalkan penggambaran objek.
function cancelObjectPolygon() {
    objectDrawing = false;
    objectDraft = [];
    objectDrawStatus('Gambar objek dibatalkan.');
    renderMainCanvas();
}

// Menggambar objek dan penandanya pada denah.
function drawLayoutObjects() {
    const t = imageTransform;
    if (!t) return;
    if (objectDrawing && objectDraft.length) {
        mainCtx.beginPath();
        objectDraft.forEach((p, i) =>
            mainCtx[i ? 'lineTo' : 'moveTo'](t.x + p[0] * t.scale, t.y + p[1] * t.scale)
        );
        mainCtx.strokeStyle = '#7c3aed';
        mainCtx.lineWidth = 3;
        mainCtx.stroke();
        if (objectDraft.length >= 3) {
            mainCtx.closePath();
            mainCtx.fillStyle = 'rgba(124,58,237,.15)';
            mainCtx.fill();
            mainCtx.setLineDash([5, 4]);
            mainCtx.stroke();
            mainCtx.setLineDash([]);
        }
        objectDraft.forEach((p, i) => {
            const x = t.x + p[0] * t.scale,
                y = t.y + p[1] * t.scale;
            mainCtx.beginPath();
            mainCtx.arc(x, y, 5, 0, Math.PI * 2);
            mainCtx.fillStyle = '#7c3aed';
            mainCtx.fill();
            mainCtx.fillText(String(i + 1), x + 8, y - 8);
        });
    }
    layoutObjects.forEach((o, i) => {
        mainCtx.beginPath();
        o.points.forEach((p, j) =>
            mainCtx[j ? 'lineTo' : 'moveTo'](t.x + p[0] * t.scale, t.y + p[1] * t.scale)
        );
        mainCtx.closePath();
        mainCtx.fillStyle = 'rgba(239,68,68,.24)';
        mainCtx.fill();
        mainCtx.strokeStyle = o.width > 0 && o.height > 0 ? '#7c3aed' : '#dc2626';
        mainCtx.lineWidth = 3;
        mainCtx.stroke();
        const p = o.points[0];
        mainCtx.fillStyle = '#7c3aed';
        mainCtx.font = 'bold 13px Arial';
        mainCtx.fillText(
            `Objek ${i + 1}`,
            t.x + p[0] * t.scale + 5,
            t.y + p[1] * t.scale - 8
        );
        if (i === selectedObject) {
            const a = o.points[selectedObjectEdge],
                b = o.points[(selectedObjectEdge + 1) % o.points.length];
            mainCtx.beginPath();
            mainCtx.moveTo(t.x + a[0] * t.scale, t.y + a[1] * t.scale);
            mainCtx.lineTo(t.x + b[0] * t.scale, t.y + b[1] * t.scale);
            mainCtx.strokeStyle = '#2563eb';
            mainCtx.lineWidth = 5;
            mainCtx.stroke();
        }
    });
}
