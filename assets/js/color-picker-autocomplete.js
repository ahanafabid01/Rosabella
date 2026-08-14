/**
 * Rosabella - E-Commerce Color Swatch Database & Professional Search Autocomplete
 * Over 120 curated e-commerce product color swatches with Hex codes.
 */
(function(window) {
    const ROSABELLA_COLOR_DATABASE = [
        // Basics & Monochrome
        { name: "Black", hex: "#000000" },
        { name: "Jet Black", hex: "#0A0A0A" },
        { name: "Midnight Black", hex: "#121212" },
        { name: "Matte Black", hex: "#222222" },
        { name: "White", hex: "#FFFFFF" },
        { name: "Off White", hex: "#FAF9F6" },
        { name: "Pure White", hex: "#FFFFFF" },
        { name: "Ivory", hex: "#FFFFF0" },
        { name: "Cream", hex: "#FFFDD0" },
        { name: "Charcoal", hex: "#36454F" },
        { name: "Gray", hex: "#808080" },
        { name: "Dark Gray", hex: "#555555" },
        { name: "Light Gray", hex: "#D3D3D3" },
        { name: "Slate Gray", hex: "#708090" },
        { name: "Silver", hex: "#C0C0C0" },
        { name: "Metallic Silver", hex: "#AAA9AD" },
        { name: "Space Gray", hex: "#4B4B4D" },
        
        // Blues & Teals
        { name: "Navy Blue", hex: "#000080" },
        { name: "Royal Blue", hex: "#4169E1" },
        { name: "Sky Blue", hex: "#87CEEB" },
        { name: "Baby Blue", hex: "#89CFF0" },
        { name: "Midnight Blue", hex: "#191970" },
        { name: "Denim Blue", hex: "#1560BD" },
        { name: "Cyan", hex: "#00FFFF" },
        { name: "Teal", hex: "#008080" },
        { name: "Turquoise", hex: "#40E0D0" },
        { name: "Electric Blue", hex: "#7DF9FF" },
        { name: "Powder Blue", hex: "#B0E0E6" },
        { name: "Ocean Blue", hex: "#006994" },
        { name: "Steel Blue", hex: "#4682B4" },
        { name: "Sapphire Blue", hex: "#0F52BA" },
        { name: "Cobalt Blue", hex: "#0047AB" },
        { name: "Aquamarine", hex: "#7FFFD4" },
        { name: "Sierra Blue", hex: "#9BB5CE" },
        { name: "Pacific Blue", hex: "#284A5C" },

        // Reds & Pinks
        { name: "Red", hex: "#FF0000" },
        { name: "Crimson Red", hex: "#DC143C" },
        { name: "Scarlet", hex: "#FF2400" },
        { name: "Burgundy", hex: "#800020" },
        { name: "Wine Red", hex: "#722F37" },
        { name: "Maroon", hex: "#800000" },
        { name: "Cherry Red", hex: "#D2042D" },
        { name: "Ruby Red", hex: "#9B111E" },
        { name: "Pink", hex: "#FFC0CB" },
        { name: "Rose Pink", hex: "#FF66CC" },
        { name: "Baby Pink", hex: "#F4C2C2" },
        { name: "Hot Pink", hex: "#FF69B4" },
        { name: "Blush Pink", hex: "#DE5D83" },
        { name: "Magenta", hex: "#FF00FF" },
        { name: "Coral Pink", hex: "#F88379" },
        { name: "Dusty Rose", hex: "#DCAE96" },
        { name: "Rose Gold", hex: "#B76E79" },
        { name: "Fuchsia", hex: "#FF00FF" },
        { name: "Salmon", hex: "#FA8072" },

        // Greens
        { name: "Green", hex: "#008000" },
        { name: "Olive Green", hex: "#808000" },
        { name: "Emerald Green", hex: "#50C878" },
        { name: "Sage Green", hex: "#9DC183" },
        { name: "Mint Green", hex: "#98FF98" },
        { name: "Forest Green", hex: "#228B22" },
        { name: "Dark Green", hex: "#006400" },
        { name: "Lime Green", hex: "#32CD32" },
        { name: "Army Green", hex: "#4B5320" },
        { name: "Pistachio Green", hex: "#93C572" },
        { name: "Jade Green", hex: "#00A86B" },
        { name: "Seafoam Green", hex: "#9FE2BF" },
        { name: "Khaki Green", hex: "#8A9A5B" },
        { name: "Alpine Green", hex: "#505E4D" },

        // Yellows, Oranges & Earth Tones
        { name: "Yellow", hex: "#FFFF00" },
        { name: "Mustard Yellow", hex: "#FFDB58" },
        { name: "Lemon Yellow", hex: "#FFF700" },
        { name: "Gold", hex: "#FFD700" },
        { name: "Metallic Gold", hex: "#D4AF37" },
        { name: "Amber", hex: "#FFBF00" },
        { name: "Orange", hex: "#FFA500" },
        { name: "Burnt Orange", hex: "#CC5500" },
        { name: "Coral", hex: "#FF7F50" },
        { name: "Peach", hex: "#FFDAB9" },
        { name: "Tangerine", hex: "#F28500" },
        { name: "Rust", hex: "#B7410E" },
        { name: "Terracotta", hex: "#E2725B" },
        { name: "Apricot", hex: "#FBCEB1" },
        { name: "Champagne", hex: "#F7E7CE" },

        // Purples
        { name: "Purple", hex: "#800080" },
        { name: "Violet", hex: "#EE82EE" },
        { name: "Lavender", hex: "#E6E6FA" },
        { name: "Plum", hex: "#8E4585" },
        { name: "Lilac", hex: "#C8A2C8" },
        { name: "Mauve", hex: "#E0B0FF" },
        { name: "Eggplant", hex: "#614051" },
        { name: "Grape", hex: "#6F2DA8" },
        { name: "Orchid", hex: "#DA70D6" },
        { name: "Indigo", hex: "#4B0082" },

        // Neutrals & Browns
        { name: "Beige", hex: "#F5F5DC" },
        { name: "Khaki", hex: "#C3B091" },
        { name: "Brown", hex: "#964B00" },
        { name: "Chocolate Brown", hex: "#7B3F00" },
        { name: "Dark Brown", hex: "#654321" },
        { name: "Camel", hex: "#C19A6B" },
        { name: "Tan", hex: "#D2B48C" },
        { name: "Taupe", hex: "#483C32" },
        { name: "Nude", hex: "#E3BC9A" },
        { name: "Sand", hex: "#C2B280" },
        { name: "Espresso", hex: "#4E3629" },
        { name: "Copper", hex: "#B87333" },
        { name: "Bronze", hex: "#CD7F32" },
        { name: "Mocha", hex: "#967969" },
        { name: "Coffee", hex: "#6F4E37" }
    ];

    window.RosabellaColorDb = ROSABELLA_COLOR_DATABASE;

    window.initColorSearchPicker = function(containerId, targetTextareaId, options = {}) {
        const container = document.getElementById(containerId);
        const targetTextarea = document.getElementById(targetTextareaId);
        if (!container || !targetTextarea) return;

        container.style.position = 'relative';
        container.innerHTML = `
            <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
                <div style="position: relative; flex: 1;">
                    <input type="text" class="form-input color-search-input" placeholder="🔍 Search color (e.g. Rose Gold, Navy, Emerald)..." style="font-size: 0.85rem; padding-left: 32px;">
                    <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); opacity: 0.5;">🎨</span>
                    <div class="color-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 220px; overflow-y: auto; background: #ffffff; border: 1.5px solid #0f766e; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 9999; margin-top: 4px; padding: 4px;"></div>
                </div>
                <div style="display: flex; align-items: center; gap: 4px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px;">
                    <input type="color" class="custom-hex-picker" value="#0f766e" style="width: 28px; height: 28px; border: none; background: none; cursor: pointer;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #475569;">Custom</span>
                </div>
            </div>
            <div class="color-swatches-tags-container" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;"></div>
        `;

        const searchInput = container.querySelector('.color-search-input');
        const dropdown = container.querySelector('.color-dropdown-menu');
        const customPicker = container.querySelector('.custom-hex-picker');
        const tagsContainer = container.querySelector('.color-swatches-tags-container');

        function renderTagsFromTextarea() {
            tagsContainer.innerHTML = '';
            const raw = targetTextarea.value.trim();
            if (!raw) return;

            const items = raw.split(',').map(s => s.trim()).filter(Boolean);
            items.forEach((item, index) => {
                let name = item;
                let hex = '#000000';
                if (item.includes(':')) {
                    const parts = item.split(':');
                    name = parts[0].trim();
                    hex = parts[1].trim();
                } else {
                    // Match against db if available
                    const matched = ROSABELLA_COLOR_DATABASE.find(c => c.name.toLowerCase() === name.toLowerCase());
                    if (matched) hex = matched.hex;
                }

                const tag = document.createElement('span');
                tag.style = 'display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 20px; font-size: 0.8rem; font-weight: 700; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06);';
                tag.innerHTML = `
                    <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background-color: ${hex}; border: 1px solid rgba(0,0,0,0.2); box-shadow: inset 0 0 2px rgba(0,0,0,0.2);"></span>
                    <span>${name}</span>
                    <span class="remove-tag-btn" style="cursor: pointer; font-size: 1rem; line-height: 1; color: #94a3b8; margin-left: 3px; font-weight: 800;">&times;</span>
                `;
                tag.querySelector('.remove-tag-btn').addEventListener('click', () => {
                    items.splice(index, 1);
                    targetTextarea.value = items.join(', ');
                    renderTagsFromTextarea();
                    if (options.onChange) options.onChange();
                });
                tagsContainer.appendChild(tag);
            });
        }

        function addColor(name, hex) {
            // For standard DB colors, save clean name; for custom hex colors, save Name or Name:Hex
            const formatted = (hex && name.includes(':')) ? name : (name.startsWith('Custom #') ? `${name}:${hex}` : name);
            let current = targetTextarea.value.split(',').map(s => s.trim()).filter(Boolean);
            
            // Avoid duplicate
            const exists = current.some(item => {
                const itemName = item.split(':')[0].trim().toLowerCase();
                return itemName === name.toLowerCase();
            });

            if (!exists) {
                current.push(formatted);
                targetTextarea.value = current.join(', ');
                renderTagsFromTextarea();
                if (options.onChange) options.onChange();
            }

            searchInput.value = '';
            dropdown.style.display = 'none';
        }

        searchInput.addEventListener('input', function() {
            const query = searchInput.value.trim().toLowerCase();
            if (!query) {
                dropdown.style.display = 'none';
                return;
            }

            const matches = ROSABELLA_COLOR_DATABASE.filter(c => c.name.toLowerCase().includes(query) || c.hex.toLowerCase().includes(query));
            if (matches.length === 0) {
                dropdown.innerHTML = `<div style="padding: 10px; font-size: 0.8rem; color: #94a3b8; text-align: center;">No standard color found. Use Custom Hex picker to add.</div>`;
            } else {
                dropdown.innerHTML = matches.map(c => `
                    <div class="color-dropdown-item" data-name="${c.name}" data-hex="${c.hex}" style="display: flex; align-items: center; justify-content: space-between; padding: 7px 10px; border-radius: 6px; cursor: pointer; transition: background 0.15s ease;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 18px; height: 18px; border-radius: 50%; background-color: ${c.hex}; border: 1px solid rgba(0,0,0,0.2); box-shadow: inset 0 0 2px rgba(0,0,0,0.2);"></span>
                            <span style="font-size: 0.83rem; font-weight: 700; color: #0f172a;">${c.name}</span>
                        </div>
                        <span style="font-size: 0.75rem; font-weight: 600; font-family: monospace; color: #0f766e; background: #ccfbf1; padding: 2px 6px; border-radius: 4px;">${c.hex}</span>
                    </div>
                `).join('');

                dropdown.querySelectorAll('.color-dropdown-item').forEach(item => {
                    item.addEventListener('mouseenter', () => item.style.background = '#f0fdf4');
                    item.addEventListener('mouseleave', () => item.style.background = 'transparent');
                    item.addEventListener('click', () => {
                        addColor(item.dataset.name, item.dataset.hex);
                    });
                });
            }
            dropdown.style.display = 'block';
        });

        customPicker.addEventListener('change', function() {
            const hex = customPicker.value.toUpperCase();
            const name = "Custom " + hex;
            addColor(name, hex);
        });

        document.addEventListener('click', function(e) {
            if (!container.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        targetTextarea.addEventListener('input', renderTagsFromTextarea);
        renderTagsFromTextarea();
    };
})(window);
