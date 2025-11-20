<!--
# 1. Default: newest, all visibility, page 1, 10 per page
GET /wp-json/recipe-auth/v1/user/recipes

# 2. Page 2, 5 per page
GET /wp-json/recipe-auth/v1/user/recipes?page=2&per_page=5

# 3. Sort: oldest first
GET /wp-json/recipe-auth/v1/user/recipes?sort=oldest

# 4. Only private recipes
GET /wp-json/recipe-auth/v1/user/recipes?visibility=private

# 5. Only public recipes
GET /wp-json/recipe-auth/v1/user/recipes?visibility=public

# 6. Private + oldest first
GET /wp-json/recipe-auth/v1/user/recipes?visibility=private&sort=oldest

# 7. Public + newest (explicit)
GET /wp-json/recipe-auth/v1/user/recipes?visibility=public&sort=newest

# 8. 20 items per page
GET /wp-json/recipe-auth/v1/user/recipes?per_page=20

# 9. Max 100 items, page 1
GET /wp-json/recipe-auth/v1/user/recipes?per_page=100

# 10. Page 3, 15 per page
GET /wp-json/recipe-auth/v1/user/recipes?page=3&per_page=15

# 11. Private + oldest + 5 per page
GET /wp-json/recipe-auth/v1/user/recipes?visibility=private&sort=oldest&per_page=5

# 12. Public + page 2
GET /wp-json/recipe-auth/v1/user/recipes?visibility=public&page=2

#================================================

/recipes/create

{
  "title": {
    "type": "string",
    "required": true,
    "example": "Chocolate Lava Cake"
  },
  "description": {
    "type": "string",
    "required": false,
    "example": "Gooey chocolate dessert with molten center."
  },
  "total_time": {
    "type": "integer",
    "required": false,
    "example": 30
  },
  "calories": {
    "type": "integer",
    "required": false,
    "example": 380
  },
  "servings": {
    "type": "integer",
    "required": false,
    "example": 4
  },
  "difficulty": {
    "type": "string",
    "required": false,
    "enum": ["easy", "medium", "hard"],
    "default": "easy",
    "example": "medium"
  },
  "visibility": {
    "type": "string",
    "required": false,
    "enum": ["public", "private"],
    "default": "public",
    "example": "private"
  },
  "protein": {
    "type": "number",
    "required": false,
    "example": 5.2
  },
  "carbs": {
    "type": "number",
    "required": false,
    "example": 42.0
  },
  "fat": {
    "type": "number",
    "required": false,
    "example": 22.0
  },
  "fiber": {
    "type": "number",
    "required": false,
    "example": 3.0
  },
  "ingredients": {
    "type": "array",
    "items": { "type": "string" },
    "required": false,
    "example": ["200g dark chocolate", "100g butter", "2 eggs"]
  },
  "instructions": {
    "type": "array",
    "items": { "type": "string" },
    "required": false,
    "example": ["Melt chocolate and butter", "Whisk eggs and sugar", "Bake at 200°C for 12 mins"]
  },
  "categories": {
    "type": "array",
    "items": { "type": ["string", "integer"] },
    "required": false,
    "example": ["dessert", "chocolate"]
  },
  "tags": {
    "type": "array",
    "items": { "type": ["string", "integer"] },
    "required": false,
    "example": ["quick", "gluten-free"]
  },
  "featured_image": {
    "type": "file",
    "required": false,
    "format": "multipart/form-data",
    "example": "(binary image file)"
  }
}

Input: featured_image Options














?search=chocolate&category=dessert






ValueActionfile

upload   Replace

image"delete"   Remove
image(not sent)  Keep current image
 -->
