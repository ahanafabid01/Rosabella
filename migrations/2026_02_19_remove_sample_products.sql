-- Remove legacy sample products shipped with old seed data
DELETE FROM products
WHERE slug IN (
    'premium-wireless-headphones',
    'smart-fitness-tracker-pro',
    'minimalist-leather-watch',
    'organic-cotton-tshirt',
    'ceramic-plant-pot-set',
    'running-shoes-ultra',
    'natural-skincare-set',
    'bestseller-book-collection'
);
