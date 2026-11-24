Local OCR assets
===============
Tesseract.js 6.0.1 (Apache-2.0): https://github.com/naptha/tesseract.js
Browser distribution and worker from npm tesseract.js@6.0.1.
Tesseract.js-core 6.0.0: https://github.com/naptha/tesseract.js-core
English model: @tesseract.js-data/eng@1.0.0/4.0.0_best_int/eng.traineddata.gz
Source: https://cdn.jsdelivr.net/npm/@tesseract.js-data/eng@1.0.0/4.0.0_best_int/eng.traineddata.gz

Serve this complete directory with the application. BrowserDimensionOCR explicitly
sets workerPath, corePath and langPath to these local assets. No external OCR
service, Swift compiler, or native OCR executable is used at runtime.
Retain the bundled license files when distributing these assets.
