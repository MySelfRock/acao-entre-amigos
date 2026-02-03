"""
PDF generation for bingo tickets
"""
import logging
import os
from datetime import datetime
from pathlib import Path
from typing import List
from app.models import PDFRequest, PDFResponse
import qrcode
from io import BytesIO

logger = logging.getLogger(__name__)


class PDFGenerator:
    """
    Generate printable PDF files for bingo tickets.

    Features:
    - 5 subcards per page (one per round)
    - QR code for verification
    - Event information
    - Clear layout for printing
    """

    def __init__(self):
        self.output_dir = Path(os.getenv("PDF_OUTPUT_DIR", "./output"))
        self.output_dir.mkdir(exist_ok=True)

    async def generate(self, request: PDFRequest) -> PDFResponse:
        """
        Generate PDF files for tickets.

        Args:
            request: PDFRequest with card data and event info

        Returns:
            PDFResponse with URLs to generated PDFs
        """
        logger.info(
            f"📄 Generating PDFs for {len(request.card_ids)} tickets "
            f"(event: {request.event_name})"
        )

        pdf_urls = []

        try:
            for idx, card_id in enumerate(request.card_ids):
                # Generate PDF file
                pdf_path = self._generate_single_pdf(
                    card_id=card_id,
                    event_id=request.event_id,
                    event_name=request.event_name,
                    event_date=request.event_date,
                    event_location=request.event_location,
                    card_index=idx + 1
                )

                if pdf_path:
                    pdf_urls.append(str(pdf_path))

                if (idx + 1) % 100 == 0:
                    logger.info(f"✅ Generated {idx + 1}/{len(request.card_ids)} PDFs")

            logger.info(f"✅ PDF generation complete: {len(pdf_urls)} files created")

            return PDFResponse(
                pdf_urls=pdf_urls,
                event_id=request.event_id,
                total_files=len(pdf_urls)
            )

        except Exception as e:
            logger.error(f"❌ PDF generation failed: {str(e)}")
            raise

    def _generate_single_pdf(
        self,
        card_id: str,
        event_id: str,
        event_name: str,
        event_date: str,
        event_location: str,
        card_index: int
    ) -> Path:
        """
        Generate a single PDF for a ticket.

        Args:
            card_id: Card UUID
            event_id: Event UUID
            event_name: Event name
            event_date: Event date
            event_location: Event location
            card_index: Card sequential number

        Returns:
            Path to generated PDF file
        """
        try:
            # For now, create a placeholder file
            # Full PDF generation with ReportLab will be implemented next
            filename = f"{card_index:05d}_{card_id[:8]}.pdf"
            filepath = self.output_dir / filename

            # Create placeholder PDF content
            pdf_content = self._create_placeholder_pdf(
                card_id, event_id, event_name, event_date, event_location, card_index
            )

            # Write file
            with open(filepath, 'w') as f:
                f.write(pdf_content)

            logger.debug(f"📄 PDF created: {filepath}")
            return filepath

        except Exception as e:
            logger.error(f"❌ Failed to generate PDF for card {card_id}: {str(e)}")
            return None

    def _create_placeholder_pdf(
        self,
        card_id: str,
        event_id: str,
        event_name: str,
        event_date: str,
        event_location: str,
        card_index: int
    ) -> str:
        """
        Create placeholder PDF content (will be replaced with ReportLab).

        Args:
            card_id: Card UUID
            event_id: Event UUID
            event_name: Event name
            event_date: Event date
            event_location: Event location
            card_index: Card sequential number

        Returns:
            PDF content as string
        """
        # Placeholder - will implement full ReportLab integration
        return f"""
        PDF PLACEHOLDER
        ================
        Event: {event_name}
        Date: {event_date}
        Location: {event_location}
        Card Number: {card_index}
        Card ID: {card_id}
        Event ID: {event_id}
        Generated: {datetime.now().isoformat()}

        [LAYOUT PLACEHOLDER]
        - Header with event info
        - 5x5 Subcards (Rounds 1-5)
        - QR Code
        - Footer with rules
        """

    def _generate_qr_code(self, data: str) -> BytesIO:
        """
        Generate QR code image.

        Args:
            data: Data to encode (JSON string with event_id and card_id)

        Returns:
            BytesIO object with QR code image
        """
        qr = qrcode.QRCode(
            version=1,
            error_correction=qrcode.constants.ERROR_CORRECT_L,
            box_size=10,
            border=4,
        )
        qr.add_data(data)
        qr.make(fit=True)

        img = qr.make_image(fill_color="black", back_color="white")

        # Convert to BytesIO
        img_io = BytesIO()
        img.save(img_io, format='PNG')
        img_io.seek(0)

        return img_io
