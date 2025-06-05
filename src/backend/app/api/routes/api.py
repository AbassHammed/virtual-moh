from fastapi import APIRouter

from app.api.routes import films

router = APIRouter()

router.include_router(films.router, prefix="/film")
