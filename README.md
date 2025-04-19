This script will help you generate labeled CAPTCHAs for object detection models.
It will generate a YOLOv8 yaml data file, folder structure, and it will create CAPTCHAs, split (as in test,train,val) them and label each character.
## Example
```
php gencaptcha/gen.php 10000 --destdir captchas
pip install -q ultralytics
yolo train data=captchas/data.yaml model=yolov8n.pt epochs=200 batch=64 workers=8 imgsz=150 device=0
```
This will create 10.000 labeled CAPTCHAs, then train a YOLOv8 model.

## Help
```
Usage: php gen.php <length> [options]
  <length>      Required: Number of samples (must be a positive number)
  --destdir     Optional: The destination directory (default: 'dataset')
  --fmt         Optional: Image format (default: 'png')
  --train       Optional: The train split ratio (default: 0.7)
  --val         Optional: The validation split ratio (default: 0.2)
  --test        Optional: The test split ratio (default: 0.1)
  -w            Optional: Image width (default: 150)
  -h            Optional: Image height (default: 40)
```

## Deploy on Google Colab

```
!apt-get install php php-gd php-mbstring > /dev/null
!git clone https://github.com/gonzalobugueno/gencaptcha
!php gencaptcha/gen.php 10000 --destdir captchas2
```
