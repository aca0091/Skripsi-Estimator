import Foundation
import Vision
import ImageIO

struct Reading: Codable {
    let text: String
    let confidence: Float
    let x: Double
    let y: Double
    let width: Double
    let height: Double
    let rotation: Int
}
func originalPoint(_ x: Double, _ y: Double, _ rotation: Int) -> (Double, Double) {
    if rotation == 90 { return (y, 1-x) }
    if rotation == -90 { return (1-y, x) }
    return (x,y)
}
do {
    guard CommandLine.arguments.count == 2,
          let source=CGImageSourceCreateWithURL(URL(fileURLWithPath:CommandLine.arguments[1]) as CFURL,nil),
          let image=CGImageSourceCreateImageAtIndex(source,0,nil) else { throw NSError(domain:"Image",code:1) }
    var readings=[Reading]()
    for (orientation,rotation) in [(CGImagePropertyOrientation.up,0),(.right,90),(.left,-90)] {
        let request=VNRecognizeTextRequest()
        request.recognitionLevel = .accurate
        request.usesLanguageCorrection = false
        request.minimumTextHeight = 0.008
        request.recognitionLanguages = ["en-US"]
        try VNImageRequestHandler(cgImage:image,orientation:orientation,options:[:]).perform([request])
        for observation in request.results ?? [] {
            guard let result=observation.topCandidates(1).first else {continue}
            let box=observation.boundingBox
            let corners=[originalPoint(box.minX,1-box.maxY,rotation),originalPoint(box.maxX,1-box.maxY,rotation),originalPoint(box.maxX,1-box.minY,rotation),originalPoint(box.minX,1-box.minY,rotation)]
            let xs=corners.map{$0.0},ys=corners.map{$0.1}
            let x=xs.min()!,y=ys.min()!
            readings.append(Reading(text:result.string,confidence:result.confidence,x:x,y:y,width:xs.max()!-x,height:ys.max()!-y,rotation:rotation))
        }
    }
    let data=try JSONEncoder().encode(readings)
    print(String(data:data,encoding:.utf8)!)
} catch {
    fputs("Unable to read image dimensions.\n",stderr)
    exit(1)
}
