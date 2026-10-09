import os
from flask import Flask, request, jsonify
from ultralytics import YOLO
import cv2

app = Flask(__name__)
MODEL_PATH = os.environ.get("YOLO_MODEL_PATH", os.path.join(os.path.dirname(__file__), "best.pt"))
model = None

def get_model():
    global model
    if model is None:
        if not os.path.isfile(MODEL_PATH):
            raise FileNotFoundError(
                f"YOLO weights not found at {MODEL_PATH}. Place your trained best.pt there "
                "or set YOLO_MODEL_PATH. A custom industrial-defect model is required."
            )
        model = YOLO(MODEL_PATH)
    return model

@app.get("/health")
def health():
    return jsonify({"ok": True, "model_configured": os.path.isfile(MODEL_PATH)})

@app.post("/detect")
def detect():
    data = request.get_json(silent=True) or {}
    path = data.get("image_path", "")
    camera = data.get("camera", "CAM-01")
    if not path or not os.path.isfile(path):
        return jsonify({"ok": False, "error": "Image path missing or not readable by Flask."}), 400
    image = cv2.imread(path)
    if image is None:
        return jsonify({"ok": False, "error": "OpenCV could not read this image."}), 400
    try:
        detector = get_model()
        results = detector.predict(source=image, conf=0.25, verbose=False)
    except Exception as exc:
        return jsonify({"ok": False, "error": str(exc)}), 503

    detections = []
    for result in results:
        names = result.names
        boxes = result.boxes
        if boxes is None:
            continue
        for box in boxes:
            cls_id = int(box.cls[0].item())
            conf = float(box.conf[0].item()) * 100
            label = str(names.get(cls_id, cls_id))
            # Match the model's class names to Good, Damaged, Cracked, Burned,
            # or configure your own product mapping for production.
            category = label.strip().title()
            detections.append({
                "category": category,
                "confidence": round(conf, 2),
                "product_id": "UNASSIGNED",
                "product_name": "Unassigned product",
                "camera": camera
            })
    if not detections:
        detections = [{"category": "Good", "confidence": 99.0, "product_id": "UNASSIGNED",
                       "product_name": "Unassigned product", "camera": camera}]
    return jsonify({"ok": True, "detections": detections})

if __name__ == "__main__":
    # Local development only. Use a production WSGI server for deployment.
    app.run(host="127.0.0.1", port=5000, debug=False)
