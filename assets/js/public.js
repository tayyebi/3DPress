// Placeholder for public JS (multi-step form, STL viewer, etc.)
// Will load three.js and STLLoader for STL viewing

// Multi-step form and STL viewer logic for 3DPress
window.ThreeDPressFormInit = function() {
    const container = document.getElementById('threedpress-form');
    if (!container) return;
    container.innerHTML = `
    <form id="threedpress-order-form" enctype="multipart/form-data">
        <div class="step step-1">
            <h3>Step 1: Upload 3D Model</h3>
            <input type="file" name="model_file" accept=".stl,.obj" required />
            <div id="stl-viewer" style="width:300px;height:200px;"></div>
            <button type="button" class="next">Next</button>
        </div>
        <div class="step step-2" style="display:none;">
            <h3>Step 2: Model Details</h3>
            <label>Measurement Unit:
                <select name="unit">
                    <option value="mm">Millimeter (mm)</option>
                    <option value="inch">Inch</option>
                </select>
            </label><br/>
            <label>Scale: <input type="number" name="scale" value="1" step="0.01" min="0.01" /></label><br/>
            <label>Length: <input type="number" name="length" step="0.01" /></label>
            <label>Width: <input type="number" name="width" step="0.01" /></label>
            <label>Height: <input type="number" name="height" step="0.01" /></label><br/>
            <label>Rotation (deg): <input type="number" name="rotation" value="0" step="1" /></label><br/>
            <label>Notes:<br/><textarea name="notes"></textarea></label><br/>
            <button type="button" class="prev">Previous</button>
            <button type="button" class="next">Next</button>
        </div>
        <div class="step step-3" style="display:none;">
            <h3>Step 3: Material & Estimate</h3>
            <label>Material:
                <select name="material" id="material-select"></select>
            </label><br/>
            <div id="estimate"></div>
            <button type="button" class="prev">Previous</button>
            <button type="submit">Submit Order</button>
        </div>
    </form>
    <div id="threedpress-success" style="display:none;"></div>
    `;
    // Multi-step logic
    const steps = container.querySelectorAll('.step');
    let currentStep = 0;
    function showStep(i) {
        steps.forEach((s, idx) => s.style.display = idx === i ? '' : 'none');
        currentStep = i;
    }
    container.querySelectorAll('.next').forEach(btn => btn.onclick = () => showStep(currentStep+1));
    container.querySelectorAll('.prev').forEach(btn => btn.onclick = () => showStep(currentStep-1));
    // Update: AJAX endpoints for frontend
    if (typeof window.ThreeDPressFormInit === 'function') {
        // Add nonce to form for security
        const nonce = document.getElementById('threedpress-form').getAttribute('data-nonce');
        // Update fetch for materials
        fetch(ajaxurl, {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=threedpress_get_materials'})
        .then(r=>r.json()).then(data => {
            const sel = document.getElementById('material-select');
            if (data && data.materials) {
                data.materials.forEach((mat,i) => {
                    const opt = document.createElement('option');
                    opt.value = mat;
                    opt.textContent = mat;
                    sel.appendChild(opt);
                });
            }
        });
        // Update estimate calculation
        function updateEstimate() {
            const form = document.getElementById('threedpress-order-form');
            const fd = new FormData(form);
            fd.append('action', 'threedpress_get_estimate');
            fd.append('nonce', nonce);
            fetch(ajaxurl, {method:'POST', body: fd})
            .then(r=>r.json()).then(data => {
                if (data && data.cost !== undefined) {
                    document.getElementById('estimate').textContent = `Estimated cost: $${data.cost}, Time: ${data.time}h`;
                } else {
                    document.getElementById('estimate').textContent = 'Could not calculate estimate.';
                }
            });
        }
        document.querySelector('select[name="material"]').addEventListener('change', updateEstimate);
        // Update form submit
        document.getElementById('threedpress-order-form').onsubmit = function(e) {
            e.preventDefault();
            const form = document.getElementById('threedpress-order-form');
            const fd = new FormData(form);
            fd.append('action', 'threedpress_submit_order');
            fd.append('nonce', nonce);
            fetch(ajaxurl, {method:'POST', body: fd})
            .then(r=>r.json()).then(data => {
                if (data.success) {
                    document.getElementById('threedpress-success').style.display = '';
                    document.getElementById('threedpress-success').textContent = data.data.message;
                    document.getElementById('threedpress-order-form').style.display = 'none';
                } else {
                    alert(data.data.message || 'Submission failed.');
                }
            });
        };
    }
    // STL Viewer logic (on file input change)
    const fileInput = container.querySelector('input[type="file"]');
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(evt) {
            // Use three.js STLLoader to render
            if (window.THREE && window.STLLoader) {
                const scene = new THREE.Scene();
                const camera = new THREE.PerspectiveCamera(75, 300/200, 0.1, 1000);
                const renderer = new THREE.WebGLRenderer({antialias:true});
                renderer.setSize(300, 200);
                const viewer = document.getElementById('stl-viewer');
                viewer.innerHTML = '';
                viewer.appendChild(renderer.domElement);
                const loader = new STLLoader();
                const geometry = loader.parse(evt.target.result);
                const material = new THREE.MeshNormalMaterial();
                const mesh = new THREE.Mesh(geometry, material);
                scene.add(mesh);
                camera.position.z = 100;
                renderer.render(scene, camera);
            }
        };
        reader.readAsArrayBuffer(file);
    });
};

// Dynamically load remote three.js and STLLoader if needed
function loadRemoteScript(url, callback) {
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.onload = callback;
    script.src = url;
    document.head.appendChild(script);
}

loadRemoteScript('https://cdn.jsdelivr.net/npm/three@0.152.2/build/three.min.js', function() {
    loadRemoteScript('https://cdn.jsdelivr.net/npm/three@0.152.2/examples/js/loaders/STLLoader.min.js', function() {
        // Now you can use THREE and STLLoader
    });
});
