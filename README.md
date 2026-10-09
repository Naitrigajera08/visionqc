# VisionQC PHP + MySQL + Flask backend

This is a runnable starter backend paired with a small working dashboard. It provides:
- PHP session login/logout with password hashing and CSRF checks
- MySQL product create/read/update/delete and price management
- Detection history, search, summary statistics and CSV export
- Alerts and machine status tables
- PHP-to-Flask image upload bridge
- Flask + OpenCV + Ultralytics YOLO inference endpoint

## Requirements
- XAMPP/WAMP or Apache + PHP 8.1+ (PDO MySQL, cURL, Fileinfo enabled)
- MySQL/MariaDB
- Python 3.10+ recommended for current Ultralytics packages
- A trained YOLO model `best.pt` with class labels matching your defect categories

## Install
1. Copy the `visionqc_backend_project` folder into your web root, e.g. `C:\\xampp\\htdocs\\visionqc_backend_project`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and import `schema.sql`.
4. Edit `config.php` if your MySQL username/password differs.
5. Visit `http://localhost/visionqc_backend_project/create_admin.php` once. It creates:
   - Email: `admin@visionqc.local`
   - Password: `ChangeMe123!`
6. Delete `create_admin.php` immediately. Sign in at `http://localhost/visionqc_backend_project/`.
7. In a terminal:
   ```bash
   cd visionqc_backend_project/flask_ai
   python -m venv .venv
   # Windows:
   .venv\\Scripts\\activate
   # macOS/Linux:
   source .venv/bin/activate
   pip install -r requirements.txt
   ```
8. Put your trained model at `flask_ai/best.pt`, or set `YOLO_MODEL_PATH` to its location.
9. Start Flask from `flask_ai`: `python app.py`.
10. Ensure PHP and Flask can read the same uploaded image path. This setup assumes both run on the same computer. If they run in separate containers/servers, send the image bytes to Flask instead of a local path.

## Important AI note
The Flask endpoint uses a real YOLO model only when you supply trained weights. There is no universal pretrained model that reliably identifies industrial cracks, burns, or damage on every product. The model's class names must match the categories your dashboard expects; map model labels to your category names in `flask_ai/app.py`.

## Security / production notes
- Change the sample admin password immediately.
- Delete `create_admin.php` after first use.
- Keep Flask bound to localhost or behind a secured reverse proxy; do not expose it publicly without authentication.
- Configure HTTPS, upload limits, retention policies, and role-based permissions before deployment.
- Do not use the demo credentials in production.
- The PHP upload handler expects PHP cURL and Fileinfo extensions enabled.
- The `uploads/` directory should be writable by PHP but not executable by the web server.
- Chart.js is served from a CDN and therefore needs internet access in the browser.

## Existing uploaded files
The original `app.js` creates simulated detections. This starter uses `dashboard.php` and `dashboard.js` for real backend calls. You can keep your original `style.css` and `app.js` as a design reference while migrating each visual section to the new API.
