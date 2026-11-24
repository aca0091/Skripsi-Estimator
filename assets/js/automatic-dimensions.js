let dimensionRequest = null;
let dimensionCandidate = null;

function dimensionSnapshot() {
    return JSON.stringify([layoutUploadVersion, roomPoints, edgeMeasurements,
        document.getElementById('dimPanjang').value, document.getElementById('dimLebar').value]);
}
function clearAutomaticDimensions() {
    if (dimensionRequest) dimensionRequest.abort();
    dimensionRequest = null;
    dimensionCandidate = null;
    document.getElementById('autoDimensionsPreview').hidden = true;
    document.getElementById('autoDimensionsStatus').textContent = '';
    document.getElementById('readDimensionsButton').disabled = false;
}
async function readAutomaticDimensions() {
    clearAutomaticDimensions();
    const status = document.getElementById('autoDimensionsStatus');
    const file = document.getElementById('inputLayoutFile').files?.[0];
    if (!file || !uploadedImageObj || calculationLoading || !RoomGeometry.valid(roomPoints)) {
        status.textContent = 'Unggah gambar dan pastikan batas ruangan sudah terdeteksi terlebih dahulu.';
        return;
    }
    if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 10 * 1024 * 1024) {
        status.textContent = 'Gunakan JPG atau PNG dengan ukuran maksimal 10 MB.';
        return;
    }
    const controller = new AbortController();
    dimensionRequest = controller;
    const snapshot = dimensionSnapshot();
    const image = uploadedImageObj;
    const points = roomPoints.map(p => p.slice());
    document.getElementById('readDimensionsButton').disabled = true;
    status.textContent = 'Membaca angka ukuran pada gambar…';
    const timeout = setTimeout(() => controller.abort(), 90000);
    try {
        const readings = await BrowserDimensionOCR.read(image, {
            signal: controller.signal,
            onProgress: message => {
                if (dimensionRequest === controller) status.textContent = message;
            }
        });
        if (dimensionRequest !== controller) return;
        if (snapshot !== dimensionSnapshot()) throw Error('Gambar atau ukuran telah berubah. Klik Baca Ukuran Otomatis kembali.');
        const result = DimensionReader.automatic(readings, points, image.width, image.height);
        if (!result || result.error) throw Error(result?.error || 'Ukuran belum terbaca. Gunakan input manual.');
        dimensionCandidate = {snapshot, width: result.width, height: result.height, points: result.points};
        document.getElementById('autoDimensionsResult').textContent =
            `Horizontal total: ${result.width.toLocaleString('id-ID')} m · Tinggi maksimum: ${result.height.toLocaleString('id-ID')} m` +
            (result.matched ? ' — ' + result.matched.map(item => `${item.label}: ${item.metres.toLocaleString('id-ID')} m`).join(' · ') : '');
        document.getElementById('autoDimensionsPreview').hidden = false;
        status.textContent = 'Angka terbaca. Periksa hasil berikut.';
    } catch (error) {
        if (dimensionRequest !== controller) return;
        status.textContent = error.name === 'AbortError'
            ? 'Pembacaan terlalu lama. Coba lagi atau isi manual.' : error.message;
    } finally {
        clearTimeout(timeout);
        if (dimensionRequest === controller) {
            dimensionRequest = null;
            document.getElementById('readDimensionsButton').disabled = false;
        }
    }
}
function applyAutomaticDimensions() {
    if (!dimensionCandidate) return;
    if (dimensionCandidate.snapshot !== dimensionSnapshot()) {
        clearAutomaticDimensions();
        document.getElementById('autoDimensionsStatus').textContent = 'Ukuran telah berubah. Baca ulang gambar sebelum menerapkan hasil.';
        return;
    }
    const {width, height, points} = dimensionCandidate;
    invalidateEdgeMeasurements();
    measuredVertexHeights = {};
    calibrationBounds = RoomGeometry.bounds(roomPoints);
    // Use line-constrained coordinates when available, or exact rectangle
    // corners instead of slightly uneven pixel edges.
    // Preserve roomPoints for the image overlay and corner selection.
    const middleX = (calibrationBounds.minX + calibrationBounds.maxX) / 2;
    const middleY = (calibrationBounds.minY + calibrationBounds.maxY) / 2;
    edgeReferencePoints = points ? points.map(p => p.slice()) : roomPoints.map(([x, y]) => [
        x < middleX ? 0 : width,
        y < middleY ? 0 : height
    ]);
    edgeCalibration = { x: 1, y: 1, complete: true };
    edgeHadPrior = true;
    document.getElementById('dimensionUnit').value = 'm';
    previousDimensionUnit = 'm';
    document.getElementById('dimPanjang').value = width;
    document.getElementById('dimLebar').value = height;
    document.getElementById('confirmScale').checked = false;
    renderVertexInputs();
    renderMainCanvas();
    runSimulasiDanHitung();
    clearAutomaticDimensions();
    setCalculationLoading(false, points
        ? `Ukuran garis diterapkan. Horizontal total ${width.toLocaleString('id-ID')} m; tinggi maksimum ${height.toLocaleString('id-ID')} m. Sisi tetap dapat diedit manual.`
        : `Ukuran otomatis diterapkan: ${width.toLocaleString('id-ID')} × ${height.toLocaleString('id-ID')} m. Sisi tetap dapat diedit manual.`);
}
