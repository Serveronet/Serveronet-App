<script src="{{ asset('sn_client_resources/js/axios.min.js') }}" type="text/javascript"></script>

<script>
    function stringToHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
            hash = hash & hash; // Convert to 32bit integer
        }
        return hash;
    }

    function hashToColor(hash) {
        const r = (hash >> 16) & 0xff;
        const g = (hash >> 8) & 0xff;
        const b = hash & 0xff;
        return `rgb(${r}, ${g}, ${b})`;
    }

    function generateAvatarSVG(username, size = 100) {
        const hash = stringToHash(username);
        const bgColor = hashToColor(hash);
        const shape = hash % 3;

        let shapeSVG = '';
        const center = size / 2;
        const radius = size / 3;

        if (shape === 0) {
            shapeSVG = `<circle cx="${center}" cy="${center}" r="${radius}" fill="white" />`;
        } else if (shape === 1) {
            shapeSVG =
                `<rect x="${center - radius}" y="${center - radius}" width="${2 * radius}" height="${2 * radius}" fill="white" />`;
        } else {
            shapeSVG =
                `<polygon points="${center},${center - radius} ${center - radius},${center + radius} ${center + radius},${center + radius}" fill="white"/>`;
        }

        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}">
            <rect width="100%" height="100%" fill="${bgColor}" />
            ${shapeSVG}
            </svg>
        `;

        return `data:image/svg+xml;base64,${btoa(svg)}`;
    }
</script>
