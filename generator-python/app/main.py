"""
FastAPI application for Bingo Ticket Generation Service
"""
import logging
from contextlib import asynccontextmanager
from fastapi import FastAPI, Depends, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import Optional

from app.security import verify_api_key
from app.bingo_generator import BingoGenerator
from app.pdf_generator import PDFGenerator
from app.models import GenerateRequest, GenerateResponse, PDFRequest, PDFResponse

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize services
bingo_generator = BingoGenerator()
pdf_generator = PDFGenerator()


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Lifespan context manager for startup/shutdown"""
    logger.info("🎰 Bingo Generator Service started")
    yield
    logger.info("🎰 Bingo Generator Service stopped")


# Create FastAPI app
app = FastAPI(
    title="Bingo Generator API",
    description="Service for generating deterministic bingo tickets and PDFs",
    version="1.0.0",
    lifespan=lifespan
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/health", tags=["Health"])
async def health_check():
    """Health check endpoint"""
    return {
        "status": "ok",
        "service": "bingo-generator",
        "version": "1.0.0"
    }


@app.post("/generator/generate", tags=["Generation"], response_model=GenerateResponse)
async def generate_tickets(
    request: GenerateRequest,
    api_key: str = Depends(verify_api_key)
):
    """
    Generate bingo tickets for an event.

    This endpoint generates all subcards (5 per ticket) for an event,
    ensuring uniqueness per round through deterministic seeding.
    """
    try:
        logger.info(
            f"📝 Generating tickets for event {request.event_id}: "
            f"{request.total_cards} cards, {request.rounds} rounds"
        )

        result = await bingo_generator.generate(request)

        logger.info(
            f"✅ Generated {result.generated} subcards "
            f"({result.generated // request.rounds} tickets)"
        )

        return result

    except Exception as e:
        logger.error(f"❌ Generation failed: {str(e)}")
        return JSONResponse(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            content={"error": str(e)}
        )


@app.post("/generator/verify", tags=["Verification"])
async def verify_subcard(
    event_id: str,
    round_number: int,
    subcard_hash: str,
    api_key: str = Depends(verify_api_key)
):
    """
    Verify if a subcard hash is valid for an event and round.
    """
    try:
        is_valid = await bingo_generator.verify(event_id, round_number, subcard_hash)

        return {
            "is_valid": is_valid,
            "event_id": event_id,
            "round": round_number
        }

    except Exception as e:
        logger.error(f"❌ Verification failed: {str(e)}")
        return JSONResponse(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            content={"error": str(e)}
        )


@app.post("/generator/pdf", tags=["PDF"], response_model=PDFResponse)
async def generate_pdf(
    request: PDFRequest,
    api_key: str = Depends(verify_api_key)
):
    """
    Generate PDF files for bingo tickets.

    Generates printable PDF files with QR codes, event info, and all 5 subcards.
    """
    try:
        logger.info(f"📄 Generating PDFs for {len(request.card_ids)} tickets")

        result = await pdf_generator.generate(request)

        logger.info(f"✅ Generated {len(result.pdf_urls)} PDF files")

        return result

    except Exception as e:
        logger.error(f"❌ PDF generation failed: {str(e)}")
        return JSONResponse(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            content={"error": str(e)}
        )


@app.get("/info", tags=["Info"])
async def service_info():
    """Get service information"""
    return {
        "name": "Bingo Generator Service",
        "version": "1.0.0",
        "capabilities": [
            "generate_tickets",
            "verify_tickets",
            "generate_pdfs"
        ],
        "bingo_config": {
            "balls": 75,
            "rounds": 5,
            "rows": 5,
            "cols": 5,
            "free_square": "center"
        }
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000, reload=True)
