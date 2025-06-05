from fastapi import APIRouter, Depends, HTTPException

router = APIRouter()

@router.get("/film/")
def get_film():
    return {"message": "Random film"}