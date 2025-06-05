from fastapi import APIRouter, Depends, HTTPException

router = APIRouter()

@router.get("/", summary="Get a random film")
def get_film():
    return {"message": "Random film"}