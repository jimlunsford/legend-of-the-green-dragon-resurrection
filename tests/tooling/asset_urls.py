"""The versioned URL the game prints for a stylesheet or script (Phase 6 task 18, N1; src/Ui/AssetUrl.php): the path,
?v= and the first 12 hex characters of the file's SHA-256."""
import hashlib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def asset_url(path):
    return path + '?v=' + hashlib.sha256((ROOT / path).read_bytes()).hexdigest()[:12]
