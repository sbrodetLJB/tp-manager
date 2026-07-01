import pytest

from tpagent.domain.errors import InvalidIdentifierError
from tpagent.util.sanitize import validate_linux_username, validate_sql_identifier


class TestValidateLinuxUsername:
    def test_accepts_valid_username(self):
        validate_linux_username("dupont2")  # ne doit pas lever

    @pytest.mark.parametrize(
        "value",
        [
            "",
            "2dupont",  # commence par un chiffre
            "_dupont",  # commence par un underscore
            "Dupont",  # majuscule
            "dupont.jean",  # point non autorisé
            "dupont-jean",  # tiret non autorisé
            "dupont jean",  # espace non autorisé
            "'; drop table users; --",  # tentative d'injection SQL
            "../../etc/passwd",  # tentative de traversal
            "a" * 33,  # trop long (> 32)
        ],
    )
    def test_rejects_invalid_usernames(self, value):
        with pytest.raises(InvalidIdentifierError):
            validate_linux_username(value)

    def test_accepts_max_length_boundary(self):
        validate_linux_username("a" * 32)  # exactement 32 -> ne doit pas lever


class TestValidateSqlIdentifier:
    def test_accepts_valid_identifier(self):
        validate_sql_identifier("dupont2_sitevitrine")

    def test_rejects_identifier_exceeding_sql_max_length(self):
        with pytest.raises(InvalidIdentifierError):
            validate_sql_identifier("a" * 64)

    def test_accepts_max_length_boundary(self):
        validate_sql_identifier("a" * 63)
