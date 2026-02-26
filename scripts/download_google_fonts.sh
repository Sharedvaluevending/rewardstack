#!/bin/bash
# Script to download Google Fonts TTF files
# Usage: ./download_google_fonts.sh

FONTS_DIR="/var/www/rewardstack/storage/app/fonts"
mkdir -p "$FONTS_DIR"

# Font names and their Google Fonts API identifiers
declare -A FONTS=(
    ["Inter"]="Inter"
    ["Roboto"]="Roboto"
    ["OpenSans"]="Open+Sans"
    ["Montserrat"]="Montserrat"
    ["Poppins"]="Poppins"
    ["PlayfairDisplay"]="Playfair+Display"
    ["Oswald"]="Oswald"
    ["Lato"]="Lato"
    ["Raleway"]="Raleway"
    ["Merriweather"]="Merriweather"
    ["SourceSansPro"]="Source+Sans+Pro"
    ["Ubuntu"]="Ubuntu"
    ["Nunito"]="Nunito"
    ["Comfortaa"]="Comfortaa"
    ["DancingScript"]="Dancing+Script"
    ["Pacifico"]="Pacifico"
)

for font_name in "${!FONTS[@]}"; do
    font_id="${FONTS[$font_name]}"
    output_file="${FONTS_DIR}/${font_name}-Regular.ttf"
    
    # Skip if file already exists and is valid
    if [ -f "$output_file" ] && [ -s "$output_file" ] && file "$output_file" | grep -q "TrueType\|OpenType"; then
        echo "✓ $font_name already exists"
        continue
    fi
    
    echo "Downloading $font_name..."
    
    # Try downloading from Google Fonts API
    # Note: This downloads a ZIP file, we'll need to extract the Regular TTF
    temp_zip="${FONTS_DIR}/temp_${font_name}.zip"
    curl -L "https://fonts.google.com/download?family=${font_id}" \
        -H "User-Agent: Mozilla/5.0" \
        -o "$temp_zip" \
        --fail --silent --show-error
    
    if [ -f "$temp_zip" ] && [ -s "$temp_zip" ]; then
        # Extract Regular TTF from zip
        unzip -q -j "$temp_zip" "*Regular.ttf" -d "$FONTS_DIR" 2>/dev/null
        if [ $? -eq 0 ]; then
            # Rename extracted file if needed
            extracted_file=$(find "$FONTS_DIR" -name "*Regular.ttf" -newer "$temp_zip" | head -1)
            if [ -n "$extracted_file" ] && [ "$extracted_file" != "$output_file" ]; then
                mv "$extracted_file" "$output_file"
            fi
            echo "  ✓ Extracted $font_name"
        else
            echo "  ✗ Failed to extract $font_name"
        fi
        rm -f "$temp_zip"
    else
        echo "  ✗ Failed to download $font_name"
        rm -f "$temp_zip"
    fi
done

echo "Done!"
