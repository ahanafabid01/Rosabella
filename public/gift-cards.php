<?php
/**
 * KARTLY - Gift Cards Page
 */
$pageTitle = 'Gift Cards';
require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="/Kartly/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <span style="color: var(--color-text);">Gift Cards</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">Gift Cards</h1>
            <p style="color: var(--color-text-light); margin-top: 0.5rem;">Give the gift of choice with KARTLY gift cards</p>
        </div>
    </section>

    <!-- Gift Cards Section -->
    <section class="section">
        <div class="container">
            <!-- Hero -->
            <div style="background: linear-gradient(135deg, var(--color-primary), #dc5603); border-radius: var(--radius-xl); padding: 3rem 2rem; text-align: center; color: white; margin-bottom: 3rem;">
                <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 1rem;">The Perfect Gift for Everyone</h2>
                <p style="max-width: 500px; margin: 0 auto 1.5rem; opacity: 0.9;">KARTLY gift cards are the perfect way to show you care. Let them choose exactly what they want.</p>
                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>No Expiry Date</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        <span>Instant Delivery</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>Secure & Safe</span>
                    </div>
                </div>
            </div>

            <!-- Gift Card Options -->
            <h3 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem; text-align: center;">Choose a Gift Card Amount</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; max-width: 900px; margin: 0 auto 3rem;">
                <!-- $25 Card -->
                <div class="gift-card-option" style="background: var(--color-bg); border: 2px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; cursor: pointer; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8V4"/><path d="M7 8V6"/><path d="M17 8V6"/></svg>
                    </div>
                    <h4 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">$25</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Perfect for small gifts</p>
                    <button class="btn btn-outline" style="margin-top: 1rem; width: 100%;" onclick="selectGiftCard(25)">Select</button>
                </div>

                <!-- $50 Card -->
                <div class="gift-card-option" style="background: var(--color-bg); border: 2px solid var(--color-primary); border-radius: var(--radius-lg); padding: 2rem; text-align: center; cursor: pointer; position: relative; transition: all var(--transition-base);">
                    <div style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: var(--color-primary); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600;">POPULAR</div>
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8V4"/><path d="M7 8V6"/><path d="M17 8V6"/></svg>
                    </div>
                    <h4 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">$50</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Great for any occasion</p>
                    <button class="btn btn-primary" style="margin-top: 1rem; width: 100%;" onclick="selectGiftCard(50)">Select</button>
                </div>

                <!-- $100 Card -->
                <div class="gift-card-option" style="background: var(--color-bg); border: 2px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; cursor: pointer; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8V4"/><path d="M7 8V6"/><path d="M17 8V6"/></svg>
                    </div>
                    <h4 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">$100</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Impressive gift value</p>
                    <button class="btn btn-outline" style="margin-top: 1rem; width: 100%;" onclick="selectGiftCard(100)">Select</button>
                </div>

                <!-- $200 Card -->
                <div class="gift-card-option" style="background: var(--color-bg); border: 2px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; cursor: pointer; transition: all var(--transition-base);" onmouseover="this.style.borderColor='var(--color-primary)'" onmouseout="this.style.borderColor='var(--color-border)'">
                    <div style="width: 60px; height: 60px; background: var(--color-primary-light); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8V4"/><path d="M7 8V6"/><path d="M17 8V6"/></svg>
                    </div>
                    <h4 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">$200</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">The ultimate gift</p>
                    <button class="btn btn-outline" style="margin-top: 1rem; width: 100%;" onclick="selectGiftCard(200)">Select</button>
                </div>
            </div>

            <!-- Custom Amount -->
            <div style="max-width: 500px; margin: 0 auto 3rem; text-align: center;">
                <h4 style="font-weight: 600; margin-bottom: 1rem;">Or Enter a Custom Amount</h4>
                <div style="display: flex; gap: 0.5rem; max-width: 300px; margin: 0 auto;">
                    <div style="position: relative; flex: 1;">
                        <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--color-text-light);">$</span>
                        <input type="number" id="customAmount" class="form-input" placeholder="Enter amount" min="10" max="500" style="padding-left: 2rem;">
                    </div>
                    <button class="btn btn-primary" onclick="selectCustomAmount()">Continue</button>
                </div>
                <p style="font-size: 0.75rem; color: var(--color-text-light); margin-top: 0.5rem;">Minimum $10, Maximum $500</p>
            </div>

            <!-- How It Works -->
            <h3 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 2rem; text-align: center;">How It Works</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: var(--color-bg-secondary); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem; font-weight: 700; color: var(--color-primary);">1</div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Choose Amount</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Select from preset amounts or enter a custom value</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: var(--color-bg-secondary); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem; font-weight: 700; color: var(--color-primary);">2</div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Add Details</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Enter recipient's email and a personal message</p>
                </div>
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: var(--color-bg-secondary); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.5rem; font-weight: 700; color: var(--color-primary);">3</div>
                    <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Send Gift</h4>
                    <p style="font-size: 0.875rem; color: var(--color-text-light);">Gift card is delivered instantly via email</p>
                </div>
            </div>

            <!-- Gift Card Form Modal -->
            <div id="giftCardModal" class="modal-overlay">
                <div class="modal" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Complete Your Gift Card Purchase</h3>
                        <button class="modal-close btn btn-ghost btn-icon" onclick="closeModal('giftCardModal')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form class="modal-body" id="giftCardForm">
                        <input type="hidden" name="amount" id="giftAmount">
                        
                        <div class="form-group">
                            <label class="form-label">Gift Card Amount</label>
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary);" id="displayAmount">$50</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="recipient_name">Recipient's Name</label>
                            <input type="text" id="recipient_name" name="recipient_name" class="form-input" placeholder="Enter recipient's name" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="recipient_email">Recipient's Email</label>
                            <input type="email" id="recipient_email" name="recipient_email" class="form-input" placeholder="Enter recipient's email" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="sender_name">Your Name</label>
                            <input type="text" id="sender_name" name="sender_name" class="form-input" placeholder="Enter your name" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="message">Personal Message (Optional)</label>
                            <textarea id="message" name="message" class="form-textarea" placeholder="Add a personal message..." rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="delivery_date">Delivery Date</label>
                            <input type="date" id="delivery_date" name="delivery_date" class="form-input" min="<?= date('Y-m-d') ?>">
                            <p style="font-size: 0.75rem; color: var(--color-text-light); margin-top: 0.25rem;">Leave blank for immediate delivery</p>
                        </div>
                    </form>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('giftCardModal')">Cancel</button>
                        <button type="submit" form="giftCardForm" class="btn btn-primary">Add to Cart</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function selectGiftCard(amount) {
            document.getElementById('giftAmount').value = amount;
            document.getElementById('displayAmount').textContent = '$' + amount;
            openModal('giftCardModal');
        }

        function selectCustomAmount() {
            const amount = parseInt(document.getElementById('customAmount').value);
            if (amount >= 10 && amount <= 500) {
                selectGiftCard(amount);
            } else {
                alert('Please enter an amount between $10 and $500');
            }
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('giftCardForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Gift card added to cart!');
            closeModal('giftCardModal');
        });
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


