<?php
/**
 * KARTLY - Size Guide
 */
$pageTitle = 'Size Guide';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="/Kartly/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <a href="/Kartly/help" style="color: var(--color-text-light);">Help</a>
                <span> / </span>
                <span style="color: var(--color-text);">Size Guide</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">Size Guide</h1>
        </div>
    </section>

    <!-- Content -->
    <section class="section">
        <div class="container" style="max-width: 1000px;">
            
            <!-- How to Measure -->
            <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 3rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">How to Measure</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                    <div style="text-align: center;">
                        <div style="width: 80px; height: 80px; background: var(--color-bg); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; border: 2px dashed var(--color-border);">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Chest</h4>
                        <p style="font-size: 0.75rem; color: var(--color-text-light);">Measure around the fullest part of your chest</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="width: 80px; height: 80px; background: var(--color-bg); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; border: 2px dashed var(--color-border);">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Waist</h4>
                        <p style="font-size: 0.75rem; color: var(--color-text-light);">Measure around your natural waistline</p>
                    </div>
                    <div style="text-align: center;">
                        <div style="width: 80px; height: 80px; background: var(--color-bg); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; border: 2px dashed var(--color-border);">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Hips</h4>
                        <p style="font-size: 0.75rem; color: var(--color-text-light);">Measure around the widest part of your hips</p>
                    </div>
                </div>
            </div>

            <!-- Size Tables -->
            <div style="margin-bottom: 3rem;">
                <!-- Category Tabs -->
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="showTable('womens')">Women's</button>
                    <button class="btn btn-secondary" onclick="showTable('mens')">Men's</button>
                    <button class="btn btn-secondary" onclick="showTable('shoes')">Shoes</button>
                </div>

                <!-- Women's Size Table -->
                <div id="womens-table" class="size-table">
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: var(--color-bg); border-radius: var(--radius-lg); overflow: hidden;">
                            <thead>
                                <tr style="background: var(--color-bg-secondary);">
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Size</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">XS</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">S</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">M</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">L</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">XL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">Chest (in)</td>
                                    <td style="padding: 1rem; text-align: center;">31-32</td>
                                    <td style="padding: 1rem; text-align: center;">33-34</td>
                                    <td style="padding: 1rem; text-align: center;">35-36</td>
                                    <td style="padding: 1rem; text-align: center;">37-39</td>
                                    <td style="padding: 1rem; text-align: center;">40-42</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">Waist (in)</td>
                                    <td style="padding: 1rem; text-align: center;">23-24</td>
                                    <td style="padding: 1rem; text-align: center;">25-26</td>
                                    <td style="padding: 1rem; text-align: center;">27-28</td>
                                    <td style="padding: 1rem; text-align: center;">29-31</td>
                                    <td style="padding: 1rem; text-align: center;">32-34</td>
                                </tr>
                                <tr>
                                    <td style="padding: 1rem; font-weight: 500;">Hips (in)</td>
                                    <td style="padding: 1rem; text-align: center;">33-34</td>
                                    <td style="padding: 1rem; text-align: center;">35-36</td>
                                    <td style="padding: 1rem; text-align: center;">37-38</td>
                                    <td style="padding: 1rem; text-align: center;">39-41</td>
                                    <td style="padding: 1rem; text-align: center;">42-44</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Men's Size Table -->
                <div id="mens-table" class="size-table" style="display: none;">
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: var(--color-bg); border-radius: var(--radius-lg); overflow: hidden;">
                            <thead>
                                <tr style="background: var(--color-bg-secondary);">
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Size</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">S</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">M</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">L</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">XL</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">XXL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">Chest (in)</td>
                                    <td style="padding: 1rem; text-align: center;">34-36</td>
                                    <td style="padding: 1rem; text-align: center;">38-40</td>
                                    <td style="padding: 1rem; text-align: center;">42-44</td>
                                    <td style="padding: 1rem; text-align: center;">46-48</td>
                                    <td style="padding: 1rem; text-align: center;">50-52</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">Waist (in)</td>
                                    <td style="padding: 1rem; text-align: center;">28-30</td>
                                    <td style="padding: 1rem; text-align: center;">32-34</td>
                                    <td style="padding: 1rem; text-align: center;">36-38</td>
                                    <td style="padding: 1rem; text-align: center;">40-42</td>
                                    <td style="padding: 1rem; text-align: center;">44-46</td>
                                </tr>
                                <tr>
                                    <td style="padding: 1rem; font-weight: 500;">Neck (in)</td>
                                    <td style="padding: 1rem; text-align: center;">14-14.5</td>
                                    <td style="padding: 1rem; text-align: center;">15-15.5</td>
                                    <td style="padding: 1rem; text-align: center;">16-16.5</td>
                                    <td style="padding: 1rem; text-align: center;">17-17.5</td>
                                    <td style="padding: 1rem; text-align: center;">18-18.5</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Shoes Size Table -->
                <div id="shoes-table" class="size-table" style="display: none;">
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; background: var(--color-bg); border-radius: var(--radius-lg); overflow: hidden;">
                            <thead>
                                <tr style="background: var(--color-bg-secondary);">
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">US</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">UK</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">EU</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">CM</th>
                                    <th style="padding: 1rem; text-align: center; font-weight: 600;">Inches</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">6</td>
                                    <td style="padding: 1rem; text-align: center;">5</td>
                                    <td style="padding: 1rem; text-align: center;">38.5</td>
                                    <td style="padding: 1rem; text-align: center;">24</td>
                                    <td style="padding: 1rem; text-align: center;">9.5</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">7</td>
                                    <td style="padding: 1rem; text-align: center;">6</td>
                                    <td style="padding: 1rem; text-align: center;">39.5</td>
                                    <td style="padding: 1rem; text-align: center;">25</td>
                                    <td style="padding: 1rem; text-align: center;">9.75</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">8</td>
                                    <td style="padding: 1rem; text-align: center;">7</td>
                                    <td style="padding: 1rem; text-align: center;">41</td>
                                    <td style="padding: 1rem; text-align: center;">26</td>
                                    <td style="padding: 1rem; text-align: center;">10.25</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">9</td>
                                    <td style="padding: 1rem; text-align: center;">8</td>
                                    <td style="padding: 1rem; text-align: center;">42</td>
                                    <td style="padding: 1rem; text-align: center;">27</td>
                                    <td style="padding: 1rem; text-align: center;">10.5</td>
                                </tr>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 1rem; font-weight: 500;">10</td>
                                    <td style="padding: 1rem; text-align: center;">9</td>
                                    <td style="padding: 1rem; text-align: center;">43</td>
                                    <td style="padding: 1rem; text-align: center;">28</td>
                                    <td style="padding: 1rem; text-align: center;">11</td>
                                </tr>
                                <tr>
                                    <td style="padding: 1rem; font-weight: 500;">11</td>
                                    <td style="padding: 1rem; text-align: center;">10</td>
                                    <td style="padding: 1rem; text-align: center;">44.5</td>
                                    <td style="padding: 1rem; text-align: center;">29</td>
                                    <td style="padding: 1rem; text-align: center;">11.5</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                <h3 style="font-weight: 600; margin-bottom: 1rem;">💡 Sizing Tips</h3>
                <ul style="color: var(--color-text-light); display: flex; flex-direction: column; gap: 0.75rem;">
                    <li>• Measure yourself in your underwear for the most accurate results</li>
                    <li>• Keep the tape measure level and not too tight</li>
                    <li>• If you're between sizes, we recommend sizing up</li>
                    <li>• Check individual product pages for specific fit information</li>
                    <li>• Contact our support team if you need help choosing the right size</li>
                </ul>
            </div>

        </div>
    </section>

    <script>
        function showTable(type) {
            // Hide all tables
            document.querySelectorAll('.size-table').forEach(table => table.style.display = 'none');
            // Show selected table
            document.getElementById(type + '-table').style.display = 'block';
            
            // Update button styles
            document.querySelectorAll('.size-table').forEach((_, i) => {
                const buttons = document.querySelectorAll('button[onclick^="showTable"]');
                buttons.forEach(btn => {
                    btn.className = btn.getAttribute('onclick').includes(type) ? 'btn btn-primary' : 'btn btn-secondary';
                });
            });
        }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


