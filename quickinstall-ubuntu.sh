set -e  # Exit immediately if a command exits with a non-zero status

# Create a temporary directory
TEMP_DIR=$(mktemp -d)

# Clone the repository
git clone https://github.com/trustos/hestiacp-nodejsv2.git "$TEMP_DIR"

# Change to the cloned directory
cd "$TEMP_DIR"

# Make the install script executable
chmod +x install.sh

# Run the install script
./install.sh

# Clean up
cd /
rm -rf "$TEMP_DIR"

echo "Installation completed and temporary files cleaned up."
