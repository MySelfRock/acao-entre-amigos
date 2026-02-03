"""
Bingo ticket generation engine with deterministic seeding
"""
import logging
import hashlib
from typing import List, Set, Tuple, Dict
from random import Random
from app.models import GenerateRequest, GenerateResponse
from app.security import hash_subcard

logger = logging.getLogger(__name__)


class BingoGenerator:
    """
    Generate deterministic bingo tickets ensuring uniqueness per round.

    Algorithm:
    1. Use seed to initialize Random (deterministic)
    2. For each ticket and round:
       - Generate 5x5 grid with unique numbers
       - Create hash of grid
       - Check uniqueness per round
       - Store hash for verification
    """

    BINGO_BALLS = 75
    COLUMNS = ["B", "I", "N", "G", "O"]
    COLUMN_RANGES = {
        "B": range(1, 16),      # 1-15
        "I": range(16, 31),     # 16-30
        "N": range(31, 46),     # 31-45
        "G": range(46, 61),     # 46-60
        "O": range(61, 76)      # 61-75
    }

    def __init__(self):
        self.generated_hashes: Dict[int, Set[str]] = {}  # round -> set of hashes
        self.subcard_data: Dict[str, Dict] = {}  # hash -> subcard data

    async def generate(self, request: GenerateRequest) -> GenerateResponse:
        """
        Generate all subcards for event.

        Args:
            request: GenerateRequest with event_id, seed, total_cards, rounds

        Returns:
            GenerateResponse with count of generated subcards and card data
        """
        logger.info(
            f"🎲 Starting generation: event_id={request.event_id}, "
            f"total_cards={request.total_cards}, rounds={request.rounds}"
        )

        # Initialize random with seed (deterministic)
        rng = Random(request.seed)

        # Initialize hash tracking per round
        self.generated_hashes = {r: set() for r in range(1, request.rounds + 1)}
        self.subcard_data = {}
        cards_data = []

        total_generated = 0

        # Generate for each ticket
        for card_index in range(request.total_cards):
            from app.security import generate_qr_code_data
            import uuid

            card_id = str(uuid.uuid4())
            qr_code = generate_qr_code_data(request.event_id, card_id)

            subcards = []

            for round_num in range(1, request.rounds + 1):
                # Generate subcard
                grid = self._generate_grid(rng, card_index, round_num)

                # Create hash
                grid_str = ",".join([",".join(row) for row in grid])
                subcard_hash = hash_subcard(grid_str)

                # Check uniqueness
                if subcard_hash in self.generated_hashes[round_num]:
                    # Very unlikely, retry with slight modification
                    logger.warning(
                        f"⚠️  Hash collision for card {card_index}, round {round_num}. Regenerating..."
                    )
                    grid = self._generate_grid(rng, card_index, round_num, retry=True)
                    grid_str = ",".join([",".join(row) for row in grid])
                    subcard_hash = hash_subcard(grid_str)

                # Store hash
                self.generated_hashes[round_num].add(subcard_hash)
                self.subcard_data[subcard_hash] = {
                    "event_id": request.event_id,
                    "card_index": card_index,
                    "round": round_num,
                    "grid": grid,
                    "hash": subcard_hash
                }

                # Add subcard to card data
                from app.models import SubcardData
                subcards.append(SubcardData(
                    round=round_num,
                    hash=subcard_hash,
                    grid=grid
                ))

                total_generated += 1

            # Add card to cards list
            from app.models import CardData
            cards_data.append(CardData(
                card_id=card_id,
                card_index=card_index + 1,
                qr_code=qr_code,
                event_id=request.event_id,
                subcards=subcards
            ))

            if (card_index + 1) % 500 == 0:
                logger.info(f"✅ Generated {card_index + 1}/{request.total_cards} tickets")

        logger.info(
            f"✅ Generation complete: {total_generated} subcards generated "
            f"({request.total_cards} tickets × {request.rounds} rounds)"
        )

        return GenerateResponse(
            generated=total_generated,
            event_id=request.event_id,
            rounds=request.rounds,
            total_cards=request.total_cards,
            cards=cards_data
        )

    def _generate_grid(
        self,
        rng: Random,
        card_index: int,
        round_num: int,
        retry: bool = False
    ) -> List[List[str]]:
        """
        Generate a single 5x5 subcard grid.

        Rules:
        - Each column has numbers from specific range
        - FREE in center (row 2, col 2)
        - No duplicates in grid
        - Deterministic based on seed

        Args:
            rng: Random instance with seed
            card_index: Index of card in event
            round_num: Round number
            retry: If True, add noise for collision retry

        Returns:
            5x5 grid as list of lists
        """
        grid = [[None for _ in range(5)] for _ in range(5)]

        # Add noise if retry
        seed_mod = hash(str(retry) + str(card_index) + str(round_num))

        # Generate for each column
        for col_idx, col_name in enumerate(self.COLUMNS):
            col_range = list(self.COLUMN_RANGES[col_name])

            # Shuffle column numbers
            if retry:
                rng.seed(rng.randint(0, 1000000) ^ seed_mod)
            rng.shuffle(col_range)

            # Fill column (5 rows)
            for row_idx in range(5):
                value = col_range[row_idx]
                grid[row_idx][col_idx] = str(value)

        # Set FREE in center
        grid[2][2] = "FREE"

        return grid

    async def verify(
        self,
        event_id: str,
        round_number: int,
        subcard_hash: str
    ) -> bool:
        """
        Verify if a subcard hash is valid.

        Args:
            event_id: Event UUID
            round_number: Round number
            subcard_hash: Hash to verify

        Returns:
            True if hash is valid for this event/round
        """
        if subcard_hash not in self.subcard_data:
            logger.warning(f"❌ Unknown hash: {subcard_hash}")
            return False

        subcard = self.subcard_data[subcard_hash]

        if subcard["event_id"] != event_id:
            logger.warning(f"❌ Hash from different event: {event_id}")
            return False

        if subcard["round"] != round_number:
            logger.warning(f"❌ Hash from different round: {round_number}")
            return False

        logger.info(f"✅ Hash verified: {subcard_hash[:16]}...")
        return True

    def get_subcard(self, subcard_hash: str) -> Dict:
        """
        Retrieve subcard data by hash.

        Args:
            subcard_hash: Hash of subcard

        Returns:
            Subcard data dict or None
        """
        return self.subcard_data.get(subcard_hash)
