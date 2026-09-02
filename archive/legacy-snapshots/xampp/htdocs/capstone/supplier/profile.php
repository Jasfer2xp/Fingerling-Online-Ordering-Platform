<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex align-items-center">
            <div class="me-4">
                <img src="<?php echo htmlspecialchars($profile['logo_url'] ?? 'https://via.placeholder.com/150'); ?>" 
                     class="rounded-circle" 
                     alt="Profile Picture" 
                     style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #e2e8f0;">
            </div>
            <div>
                <h6 class="mb-2">Profile Picture</h6>
                <p class="text-muted small mb-2">Upload a logo or profile picture for your business</p>
                <input type="file" class="form-control form-control-sm" name="logo" accept="image/jpeg,image/png">
                <div class="form-text">JPG, PNG format, max 2MB</div>
                <button type="submit" name="action" value="upload_logo" class="btn btn-outline-primary btn-sm mt-2">
                    <i class="fas fa-upload me-1"></i>Upload Picture
                </button>
            </div>
        </div>
    </div>
</div>