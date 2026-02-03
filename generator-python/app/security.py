"""
Security utilities for API authentication
"""
import os
import hmac
import hashlib
from datetime import datetime, timedelta
import logging
from fastapi import HTTPException, Header, status
from typing import Optional

logger = logging.getLogger(__name__)

# Get API key from environment
API_KEY = os.getenv("API_KEY", "dev-api-key")
SECRET_KEY = os.getenv("SECRET_KEY", "dev-secret-key")
ALGORITHM = os.getenv("ALGORITHM", "HS256")


async def verify_api_key(x_api_key: str = Header(None)) -> str:
    """
    Verify API key from request header.

    Args:
        x_api_key: API key from X-API-KEY header

    Returns:
        API key if valid

    Raises:
        HTTPException: If API key is missing or invalid
    """
    if not x_api_key:
        logger.warning("❌ Missing API key in request")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="API key missing",
            headers={"WWW-Authenticate": "Bearer"},
        )

    if x_api_key != API_KEY:
        logger.warning(f"❌ Invalid API key provided: {x_api_key[:10]}...")
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Invalid API key",
        )

    return x_api_key


def create_hmac_signature(
    message: str,
    secret: Optional[str] = None
) -> str:
    """
    Create HMAC-SHA256 signature for message.

    Args:
        message: Message to sign
        secret: Secret key (uses SECRET_KEY if None)

    Returns:
        Hex-encoded signature
    """
    if secret is None:
        secret = SECRET_KEY

    signature = hmac.new(
        secret.encode(),
        message.encode(),
        hashlib.sha256
    ).hexdigest()

    return signature


def verify_hmac_signature(
    message: str,
    signature: str,
    secret: Optional[str] = None
) -> bool:
    """
    Verify HMAC-SHA256 signature.

    Args:
        message: Original message
        signature: Signature to verify
        secret: Secret key (uses SECRET_KEY if None)

    Returns:
        True if signature is valid
    """
    if secret is None:
        secret = SECRET_KEY

    expected_signature = create_hmac_signature(message, secret)
    return hmac.compare_digest(signature, expected_signature)


def create_deterministic_seed(
    event_id: str,
    round_number: int,
    card_index: int,
    server_secret: Optional[str] = None
) -> str:
    """
    Create deterministic seed for ticket generation.

    Args:
        event_id: Event UUID
        round_number: Round number (1-5)
        card_index: Card index in event
        server_secret: Server secret (never exposed)

    Returns:
        SHA-256 hash seed
    """
    if server_secret is None:
        server_secret = SECRET_KEY

    combined = f"{event_id}:{round_number}:{card_index}:{server_secret}"
    seed = hashlib.sha256(combined.encode()).hexdigest()

    return seed


def hash_subcard(grid_data: str) -> str:
    """
    Create hash of subcard grid for uniqueness verification.

    Args:
        grid_data: Serialized grid data

    Returns:
        SHA-256 hash
    """
    return hashlib.sha256(grid_data.encode()).hexdigest()


def validate_seed_format(seed: str) -> bool:
    """
    Validate seed format (should be hex string of reasonable length).

    Args:
        seed: Seed to validate

    Returns:
        True if valid format
    """
    if not isinstance(seed, str):
        return False
    if len(seed) < 32:
        return False
    try:
        int(seed, 16)
        return True
    except ValueError:
        return False
