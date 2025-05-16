// Admin STL viewer logic for 3DPress order table
window.ThreeDPressAdminInit = function() {
    document.querySelectorAll('.three-dpress-stl-viewer').forEach(function(viewer) {
        const url = viewer.getAttribute('data-stl-url');
        if (!url || !window.THREE || !window.STLLoader) return;
        const width = viewer.offsetWidth || 200;
        const height = viewer.offsetHeight || 150;
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, width/height, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({antialias:true});
        renderer.setSize(width, height);
        viewer.innerHTML = '';
        viewer.appendChild(renderer.domElement);
        const loader = new STLLoader();
        fetch(url).then(r=>r.arrayBuffer()).then(buf => {
            const geometry = loader.parse(buf);
            const material = new THREE.MeshNormalMaterial();
            const mesh = new THREE.Mesh(geometry, material);
            scene.add(mesh);
            camera.position.z = 100;
            renderer.render(scene, camera);
        });
    });
};
