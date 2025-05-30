document.addEventListener('DOMContentLoaded', () => {
    const scene = document.querySelector('a-scene');
    const arContainer = document.getElementById('ar-container');
    const loading = document.getElementById('loading');
    const stopArBtn = document.getElementById('stop-ar-btn');
    const poiSelect = document.getElementById('poi-select');
    const venueData = window.venueData;
    const poiData = window.poiData;

    // Check if running on a mobile device
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    // Object to store entities for dynamic updates
    let venueEntity = null;
    let venueText = null;
    const poiEntities = {};
    const poiTexts = {};

    // Check if geolocation is supported
    if (!navigator.geolocation) {
        loading.style.display = 'none';
        if (isMobile) {
            alert('Geolocation is not supported by your browser. Please use a device with GPS enabled.');
        }
        return;
    }

    // Function to clear the scene
    function clearScene() {
        if (venueEntity) scene.removeChild(venueEntity);
        if (venueText) scene.removeChild(venueText);
        Object.values(poiEntities).forEach(entity => scene.removeChild(entity));
        Object.values(poiTexts).forEach(text => scene.removeChild(text));
    }

    // Function to render the scene based on selected POI
    function renderScene(selectedPoiId = 'all') {
        clearScene();

        // Add venue marker if coordinates exist
        if (venueData.latitude && venueData.longitude) {
            venueEntity = document.createElement('a-entity');
            venueEntity.setAttribute('gps-new-entity-place', `latitude: ${venueData.latitude}; longitude: ${venueData.longitude}`);
            venueEntity.setAttribute('gltf-model', '#marker-model');
            venueEntity.setAttribute('scale', '5 5 5');
            venueEntity.setAttribute('rotation', '0 0 0');
            scene.appendChild(venueEntity);

            venueText = document.createElement('a-text');
            venueText.setAttribute('gps-new-entity-place', `latitude: ${venueData.latitude}; longitude: ${venueData.longitude}`);
            venueText.setAttribute('value', venueData.name);
            venueText.setAttribute('align', 'center');
            venueText.setAttribute('color', '#ffffff');
            venueText.setAttribute('scale', '10 10 10');
            scene.appendChild(venueText);
        }

        // Add POI markers
        poiData.forEach(poi => {
            if (poi.latitude && poi.longitude) {
                const isSelected = selectedPoiId !== 'all' && poi.poi_id == selectedPoiId;
                const poiEntity = document.createElement('a-entity');
                poiEntity.setAttribute('gps-new-entity-place', `latitude: ${poi.latitude}; longitude: ${poi.longitude}`);
                poiEntity.setAttribute('gltf-model', '#marker-model');
                poiEntity.setAttribute('scale', isSelected ? '5 5 5' : '3 3 3'); // Highlight selected POI
                poiEntity.setAttribute('rotation', '0 0 0');
                scene.appendChild(poiEntity);
                poiEntities[poi.poi_id] = poiEntity;

                const poiText = document.createElement('a-text');
                poiText.setAttribute('gps-new-entity-place', `latitude: ${poi.latitude}; longitude: ${poi.longitude}`);
                poiText.setAttribute('value', poi.poi_name);
                poiText.setAttribute('align', 'center');
                poiText.setAttribute('color', isSelected ? '#ffff00' : '#ff0000'); // Yellow for selected, red for others
                poiText.setAttribute('scale', isSelected ? '7 7 7' : '5 5 5');
                if (selectedPoiId === 'all' || isSelected) {
                    scene.appendChild(poiText);
                }
                poiTexts[poi.poi_id] = poiText;
            }
        });
    }

    // Request location permission
    navigator.geolocation.getCurrentPosition(
        () => {
            // Hide loading and show AR container
            loading.style.display = 'none';
            arContainer.style.display = 'block';
            if (isMobile) {
                stopArBtn.style.display = 'block';
            }

            // Initial render
            renderScene();

            // Update scene when POI selection changes
            poiSelect.addEventListener('change', () => {
                const selectedPoiId = poiSelect.value;
                renderScene(selectedPoiId);
            });
        },
        (error) => {
            loading.style.display = 'none';
            if (isMobile) {
                alert('Please enable location services in your browser settings to use AR navigation. Error: ' + error.message);
            }
        }
    );

    // Function to stop AR navigation
    function stopARNavigation() {
        clearScene();
        window.location.href = 'index.php';
    }

    // Add event listener for the stop button
    stopArBtn.addEventListener('click', stopARNavigation);

    // Define a simple 3D model for markers (e.g., a cube)
    AFRAME.registerComponent('marker-model', {
        init: function() {
            this.el.setAttribute('geometry', { primitive: 'box' });
            this.el.setAttribute('material', { color: '#00ff00', opacity: 0.7 });
        }
    });
});