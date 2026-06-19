<?php
/**
 * KARTLY - Careers
 */
$pageTitle = 'Careers';
require_once 'includes/header.php';
?>

    <!-- Hero -->
    <section style="background: linear-gradient(135deg, var(--color-primary), #dc5603); padding: 4rem 0; color: white; text-align: center;">
        <div class="container">
            <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem;">Join Our Team</h1>
            <p style="max-width: 600px; margin: 0 auto; opacity: 0.9;">Be part of something amazing. Build your career with KARTLY.</p>
        </div>
    </section>

    <!-- Why Join Us -->
    <section class="section">
        <div class="container" style="max-width: 1000px;">
            
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 2rem; text-align: center;">Why Work at KARTLY?</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 4rem;">
                <div style="text-align: center; padding: 1.5rem;">
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Competitive Salary</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Industry-leading compensation packages with annual reviews</p>
                </div>
                <div style="text-align: center; padding: 1.5rem;">
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Health Benefits</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Comprehensive health, dental, and vision insurance</p>
                </div>
                <div style="text-align: center; padding: 1.5rem;">
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Flexible Hours</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Work-life balance with remote options available</p>
                </div>
                <div style="text-align: center; padding: 1.5rem;">
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Growth Opportunities</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Career development programs and mentorship</p>
                </div>
            </div>

            <!-- Open Positions -->
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Open Positions</h2>
            
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php
                $jobs = [
                    ['title' => 'Senior Frontend Developer', 'department' => 'Engineering', 'location' => 'Remote', 'type' => 'Full-time'],
                    ['title' => 'Product Manager', 'department' => 'Product', 'location' => 'New York, NY', 'type' => 'Full-time'],
                    ['title' => 'UX Designer', 'department' => 'Design', 'location' => 'San Francisco, CA', 'type' => 'Full-time'],
                    ['title' => 'Customer Support Specialist', 'department' => 'Support', 'location' => 'Remote', 'type' => 'Full-time'],
                    ['title' => 'Marketing Coordinator', 'department' => 'Marketing', 'location' => 'New York, NY', 'type' => 'Full-time'],
                    ['title' => 'Data Analyst', 'department' => 'Analytics', 'location' => 'Remote', 'type' => 'Full-time'],
                ];
                foreach ($jobs as $job): ?>
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <div>
                        <h4 style="font-weight: 600; margin-bottom: 0.25rem;"><?= htmlspecialchars($job['title']) ?></h4>
                        <p style="font-size: 0.875rem; color: var(--color-text-light);">
                            <?= htmlspecialchars($job['department']) ?> • <?= htmlspecialchars($job['location']) ?> • <?= htmlspecialchars($job['type']) ?>
                        </p>
                    </div>
                    <a href="#apply" class="btn btn-primary">Apply Now</a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Apply Form -->
            <div id="apply" style="margin-top: 4rem; padding-top: 3rem; border-top: 1px solid var(--color-border);">
                <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">General Application</h2>
                <p style="color: var(--color-text-light); margin-bottom: 2rem;">Don't see a position that fits? Send us your resume and we'll keep you in mind for future opportunities.</p>
                
                <form style="max-width: 500px;">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" placeholder="Enter your name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position Interested In</label>
                        <select class="form-select">
                            <option>Engineering</option>
                            <option>Product</option>
                            <option>Design</option>
                            <option>Marketing</option>
                            <option>Support</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Resume/CV</label>
                        <input type="file" class="form-input" accept=".pdf,.doc,.docx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cover Letter (Optional)</label>
                        <textarea class="form-textarea" rows="4" placeholder="Tell us about yourself..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">Submit Application</button>
                </form>
            </div>

        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
