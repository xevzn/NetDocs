import base64
import hashlib
import os

from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from cryptography.exceptions import InvalidTag

PAYLOAD_MAGIC = b"NDR2"
MASTER_KEY = os.environ.get("VAULT_MASTER_KEY")
if not MASTER_KEY:
    raise RuntimeError("VAULT_MASTER_KEY is not configured")
DERIVED_KEY = hashlib.sha256(MASTER_KEY.encode("utf-8")).digest()


def decrypt_password(encoded_payload):
    if not encoded_payload:
        return None

    try:
        decoded = base64.b64decode(encoded_payload, validate=True)
        if len(decoded) < 32 or not decoded.startswith(PAYLOAD_MAGIC):
            return None

        nonce = decoded[4:16]
        tag = decoded[16:32]
        ciphertext = decoded[32:]
        plaintext = AESGCM(DERIVED_KEY).decrypt(nonce, ciphertext + tag, PAYLOAD_MAGIC)
        return plaintext.decode("utf-8")
    except (InvalidTag, ValueError, TypeError, UnicodeDecodeError):
        return None