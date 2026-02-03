"""
PDF generation for bingo tickets with custom layout support
"""
import logging
import os
import json
from datetime import datetime
from pathlib import Path
from typing import List, Dict, Optional
from app.models import PDFRequest, PDFResponse, SubcardData
import qrcode
from io import BytesIO
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader
from PIL import Image as PILImage

logger = logging.getLogger(__name__)


class PDFGenerator:
    """
    Generate printable PDF files for bingo tickets with ReportLab.

    Features:
    - 5 subcards per page (one per round)
    - QR code for verification
    - Custom background layout support
    - Event information
    - Professional layout for printing
    - Uses actual subcard data from database
    """

    # Column definitions for bingo: B, I, N, G, O
    COLUMNS = ['B', 'I', 'N', 'G', 'O']

    def __init__(self):
        self.output_dir = Path(os.getenv("PDF_OUTPUT_DIR", "./output"))
        self.output_dir.mkdir(exist_ok=True)

    async def generate(self, request: PDFRequest) -> PDFResponse:
        """
        Generate PDF files for tickets with real card data.

        Args:
            request: PDFRequest with card data, event info, and layout config

        Returns:
            PDFResponse with URLs to generated PDFs
        """
        logger.info(
            f"📄 Generating PDFs for {len(request.cards)} tickets "
            f"(event: {request.event_name})"
        )

        pdf_urls = []

        try:
            for idx, card_data in enumerate(request.cards):
                # Generate PDF file with actual card data
                pdf_path = await self._generate_single_pdf(
                    card_id=card_data.card_id,
                    card_index=card_data.card_index,
                    subcards=card_data.subcards,
                    event_id=request.event_id,
                    event_name=request.event_name,
                    event_date=request.event_date,
                    event_location=request.event_location,
                    layout=request.layout,
                    layout_config=request.layout_config,
                )

                if pdf_path:
                    pdf_urls.append(str(pdf_path))

                if (idx + 1) % 100 == 0:
                    logger.info(f"✅ Generated {idx + 1}/{len(request.cards)} PDFs")

            logger.info(f"✅ PDF generation complete: {len(pdf_urls)} files created")

            return PDFResponse(
                pdf_urls=pdf_urls,
                event_id=request.event_id,
                total_files=len(pdf_urls)
            )

        except Exception as e:
            logger.error(f"❌ PDF generation failed: {str(e)}")
            raise

    async def _generate_single_pdf(
        self,
        card_id: str,
        card_index: int,
        subcards: List[SubcardData],
        event_id: str,
        event_name: str,
        event_date: Optional[str],
        event_location: Optional[str],
        layout: str = "default",
        layout_config=None
    ) -> Optional[Path]:
        """
        Generate a single PDF for a ticket.

        Args:
            card_id: Card UUID
            card_index: Card sequential number
            subcards: List of SubcardData with grids
            event_id: Event UUID
            event_name: Event name
            event_date: Event date
            event_location: Event location
            layout: Layout template name
            layout_config: Custom layout configuration

        Returns:
            Path to generated PDF file or None if failed
        """
        try:
            filename = f"{card_index:05d}_{card_id[:8]}.pdf"
            filepath = self.output_dir / filename

            # Create PDF with ReportLab
            self._create_pdf_with_reportlab(
                filepath=filepath,
                card_id=card_id,
                card_index=card_index,
                subcards=subcards,
                event_id=event_id,
                event_name=event_name,
                event_date=event_date,
                event_location=event_location,
                layout=layout,
                layout_config=layout_config,
            )

            logger.debug(f"📄 PDF created: {filepath}")
            return filepath

        except Exception as e:
            logger.error(f"❌ Failed to generate PDF for card {card_id}: {str(e)}")
            return None

    def _create_pdf_with_reportlab(
        self,
        filepath: Path,
        card_id: str,
        card_index: int,
        subcards: List[SubcardData],
        event_id: str,
        event_name: str,
        event_date: Optional[str],
        event_location: Optional[str],
        layout: str,
        layout_config=None,
    ) -> None:
        """
        Create PDF using ReportLab with custom layout and actual card data.
        """
        from reportlab.lib.units import mm

        # Create canvas
        c = canvas.Canvas(str(filepath), pagesize=A4)
        width, height = A4

        # Margins
        margin_top = 15 * mm
        margin_left = 10 * mm
        margin_bottom = 10 * mm

        # Get layout configuration (use provided or get default)
        if layout_config:
            config = self._merge_layout_config(layout_config)
        else:
            config = self._get_layout_config(layout)

        # Draw background if available
        self._draw_background(c, filepath, config, width, height)

        # Header section
        header_y = height - margin_top
        self._draw_header(c, event_name, event_date, event_location, header_y, config)

        # Card info section
        info_y = header_y - 30 * mm
        self._draw_card_info(c, card_id, card_index, info_y, config)

        # QR Code
        qr_y = info_y - 35 * mm
        self._draw_qr_code(c, card_id, event_id, qr_y, config)

        # 5 Subcards (5 rounds) - use actual subcard data
        subcard_y = qr_y - 10 * mm
        subcard_height = 35 * mm
        subcard_width = (width - margin_left - 10 * mm) / 3

        for idx, subcard in enumerate(subcards):
            if idx >= 5:  # Maximum 5 subcards per page
                break

            round_num = subcard.round
            col = (idx) % 3
            row = (idx) // 3

            x = margin_left + (col * (subcard_width + 3 * mm))
            y = subcard_y - (row * (subcard_height + 5 * mm))

            self._draw_subcard_with_data(
                c, round_num, x, y, subcard_width, subcard_height, subcard.grid, config
            )

        # Footer
        footer_y = margin_bottom
        self._draw_footer(c, config, footer_y)

        c.save()

    def _draw_header(
        self,
        c: canvas.Canvas,
        event_name: str,
        event_date: str,
        event_location: str,
        y: float,
        config: Dict,
    ) -> None:
        """Draw header section with event information."""
        from reportlab.lib.units import mm

        c.setFont("Helvetica-Bold", 16)
        c.setFillColor(colors.HexColor(config.get('header_color', '#3498DB')))
        c.drawString(20 * mm, y, event_name)

        c.setFont("Helvetica", 10)
        c.setFillColor(colors.black)
        info_text = f"Data: {event_date or 'N/A'} | Local: {event_location or 'N/A'}"
        c.drawString(20 * mm, y - 7 * mm, info_text)

    def _draw_card_info(
        self,
        c: canvas.Canvas,
        card_id: str,
        card_index: int,
        y: float,
        config: Dict,
    ) -> None:
        """Draw card identification."""
        from reportlab.lib.units import mm

        c.setFont("Helvetica-Bold", 12)
        c.setFillColor(colors.black)
        c.drawString(20 * mm, y, f"Cartela Nº: {card_index:05d}")
        c.drawString(80 * mm, y, f"ID: {card_id[:8].upper()}")

    def _draw_qr_code(
        self,
        c: canvas.Canvas,
        card_id: str,
        event_id: str,
        y: float,
        config: Dict,
    ) -> None:
        """Draw QR code."""
        from reportlab.lib.units import mm

        qr_data = json.dumps({"event_id": event_id, "card_id": card_id})
        qr = qrcode.QRCode(
            version=1,
            error_correction=qrcode.constants.ERROR_CORRECT_L,
            box_size=8,
            border=2,
        )
        qr.add_data(qr_data)
        qr.make(fit=True)

        img = qr.make_image(fill_color="black", back_color="white")
        img_bytes = BytesIO()
        img.save(img_bytes, format='PNG')
        img_bytes.seek(0)

        # Draw QR code (30x30mm)
        c.drawImage(ImageReader(img_bytes), 170 * mm, y - 30 * mm, width=30 * mm, height=30 * mm)

    def _draw_subcard_with_data(
        self,
        c: canvas.Canvas,
        round_num: int,
        x: float,
        y: float,
        width: float,
        height: float,
        grid: List[List[str]],
        config: Dict,
    ) -> None:
        """Draw a single 5x5 subcard with actual data."""
        from reportlab.lib.units import mm

        # Title
        c.setFont("Helvetica-Bold", 11)
        c.setFillColor(colors.HexColor(config.get('header_color', '#3498DB')))
        c.drawString(x + 2 * mm, y, f"Rodada {round_num}")

        # Grid
        cell_width = (width - 4 * mm) / 5
        cell_height = (height - 8 * mm) / 5
        grid_y = y - 5 * mm

        c.setFont("Helvetica", 8)
        c.setFillColor(colors.black)

        for row_idx, row in enumerate(grid):
            for col_idx, value in enumerate(row):
                cell_x = x + 2 * mm + (col_idx * cell_width)
                cell_y = grid_y - (row_idx * cell_height)

                # Draw cell border
                c.rect(cell_x, cell_y - cell_height, cell_width, cell_height, stroke=1, fill=0)

                # Fill FREE square with special color
                if value == "FREE":
                    c.setFillColor(colors.HexColor(config.get('free_space_color', '#FFD700')))
                    c.rect(cell_x, cell_y - cell_height, cell_width, cell_height, stroke=1, fill=1)
                    c.setFillColor(colors.black)

                # Draw number centered in cell
                text_x = cell_x + cell_width / 2
                text_y = cell_y - cell_height / 2 - 1.5 * mm

                if value != "FREE":
                    c.drawCentredString(text_x, text_y, str(value))
                else:
                    c.drawCentredString(text_x, text_y, "FREE")

    def _draw_footer(
        self,
        c: canvas.Canvas,
        config: Dict,
        y: float,
    ) -> None:
        """Draw footer section."""
        from reportlab.lib.units import mm

        c.setFont("Helvetica", 8)
        c.setFillColor(colors.grey)

        footer_text = config.get('footer_text', 'Ação entre Amigos - Sistema de Bingo Híbrido')
        c.drawString(20 * mm, y, footer_text)

        # Page number and timestamp
        c.drawString(170 * mm, y, f"Gerado: {datetime.now().strftime('%d/%m/%Y %H:%M')}")

    def _draw_background(
        self,
        c: canvas.Canvas,
        filepath: Path,
        config: Dict,
        width: float,
        height: float,
    ) -> None:
        """Draw background image if available."""
        background_file = config.get('background_file')

        if not background_file or not os.path.exists(background_file):
            return

        try:
            c.drawImage(
                ImageReader(background_file),
                0, 0,
                width=width,
                height=height,
                preserveAspectRatio=True,
                opacity=0.1,  # Subtle background
            )
        except Exception as e:
            logger.warning(f"⚠️  Could not draw background: {str(e)}")

    def _get_layout_config(self, layout: str) -> Dict:
        """
        Get layout configuration by name.

        In production, this will fetch from database.
        """
        layouts = {
            'default': {
                'bg_color': '#FFFFFF',
                'text_color': '#000000',
                'header_color': '#3498DB',
                'free_space_color': '#FFD700',
                'footer_text': 'Ação entre Amigos - Sistema de Bingo Híbrido',
            },
            'dark': {
                'bg_color': '#1A1A1A',
                'text_color': '#FFFFFF',
                'header_color': '#E74C3C',
                'free_space_color': '#F39C12',
                'footer_text': 'Ação entre Amigos',
            },
            'pastel': {
                'bg_color': '#FFF9E6',
                'text_color': '#333333',
                'header_color': '#9B59B6',
                'free_space_color': '#F1C40F',
                'footer_text': 'Bingo - Edição Especial',
            },
        }

        return layouts.get(layout, layouts['default'])

    def _merge_layout_config(self, layout_config) -> Dict:
        """
        Merge custom layout config with defaults.

        Args:
            layout_config: LayoutConfig object from request

        Returns:
            Merged configuration dictionary
        """
        defaults = self._get_layout_config('default')

        # Convert Pydantic model to dict if needed
        if hasattr(layout_config, 'dict'):
            custom = layout_config.dict(exclude_none=True)
        else:
            custom = layout_config

        return {**defaults, **custom}
