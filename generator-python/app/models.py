"""
Pydantic models for request/response validation
"""
from pydantic import BaseModel, Field, validator
from typing import List, Optional
from datetime import datetime


class GenerateRequest(BaseModel):
    """Request model for ticket generation"""
    event_id: str = Field(..., description="UUID of the event")
    seed: str = Field(..., description="Global seed for deterministic generation")
    total_cards: int = Field(..., ge=1, le=100000, description="Number of tickets to generate")
    rounds: int = Field(default=5, ge=1, le=10, description="Number of rounds per ticket")

    class Config:
        schema_extra = {
            "example": {
                "event_id": "550e8400-e29b-41d4-a716-446655440000",
                "seed": "hash_seed_here",
                "total_cards": 2000,
                "rounds": 5
            }
        }


class GenerateResponse(BaseModel):
    """Response model for ticket generation"""
    status: str = Field(default="ok", description="Status of the operation")
    generated: int = Field(..., description="Number of subcards generated (total_cards * rounds)")
    event_id: str = Field(..., description="Event ID")
    rounds: int = Field(..., description="Number of rounds")
    total_cards: int = Field(..., description="Number of cards generated")
    cards: List[CardData] = Field(default_factory=list, description="Generated card data")
    timestamp: datetime = Field(default_factory=datetime.utcnow)

    class Config:
        schema_extra = {
            "example": {
                "status": "ok",
                "generated": 10000,
                "event_id": "550e8400-e29b-41d4-a716-446655440000",
                "rounds": 5,
                "total_cards": 2000,
                "cards": [],
                "timestamp": "2024-02-03T12:00:00"
            }
        }


class PDFRequest(BaseModel):
    """Request model for PDF generation"""
    event_id: str = Field(..., description="UUID of the event")
    event_name: str = Field(..., description="Name of the event")
    event_date: Optional[str] = Field(None, description="Event date")
    event_location: Optional[str] = Field(None, description="Event location")
    card_ids: List[str] = Field(..., description="List of card IDs to generate PDFs for")
    layout: str = Field(default="default", description="PDF layout template")

    @validator('card_ids')
    def validate_card_ids(cls, v):
        if len(v) == 0:
            raise ValueError("At least one card ID must be provided")
        if len(v) > 10000:
            raise ValueError("Maximum 10000 cards per request")
        return v

    class Config:
        schema_extra = {
            "example": {
                "event_id": "550e8400-e29b-41d4-a716-446655440000",
                "event_name": "Bingo Beneficente",
                "event_date": "2024-05-10",
                "event_location": "São Paulo, SP",
                "card_ids": ["card-001", "card-002"],
                "layout": "default"
            }
        }


class PDFResponse(BaseModel):
    """Response model for PDF generation"""
    status: str = Field(default="ok", description="Status of the operation")
    pdf_urls: List[str] = Field(..., description="URLs to generated PDF files")
    event_id: str = Field(..., description="Event ID")
    total_files: int = Field(..., description="Number of PDF files generated")
    timestamp: datetime = Field(default_factory=datetime.utcnow)

    class Config:
        schema_extra = {
            "example": {
                "status": "ok",
                "pdf_urls": [
                    "http://localhost:8001/pdfs/card-001.pdf",
                    "http://localhost:8001/pdfs/card-002.pdf"
                ],
                "event_id": "550e8400-e29b-41d4-a716-446655440000",
                "total_files": 2,
                "timestamp": "2024-02-03T12:00:00"
            }
        }


class SubcardData(BaseModel):
    """Model for subcard data"""
    round: int
    hash: str
    grid: List[List[str]]  # 5x5 grid of numbers or "FREE"

    class Config:
        schema_extra = {
            "example": {
                "round": 1,
                "hash": "abc123def456...",
                "grid": [
                    ["1", "16", "31", "46", "61"],
                    ["2", "17", "32", "47", "62"],
                    ["3", "18", "FREE", "48", "63"],
                    ["4", "19", "33", "49", "64"],
                    ["5", "20", "34", "50", "65"]
                ]
            }
        }


class CardData(BaseModel):
    """Model for complete card data"""
    card_id: str
    card_index: int
    qr_code: str
    event_id: str
    subcards: List[SubcardData]

    class Config:
        schema_extra = {
            "example": {
                "card_id": "550e8400-e29b-41d4-a716-446655440000",
                "card_index": 1,
                "qr_code": "QRCODE_DATA",
                "event_id": "550e8400-e29b-41d4-a716-446655440111",
                "subcards": []
            }
        }
