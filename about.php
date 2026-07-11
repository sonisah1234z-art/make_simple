<?php
session_start();
include 'header.php';
?>

<div class="about-page">
    <section class="about-hero card shadow-sm">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <div class="eyebrow">About Us</div>
                <h1>Compassionate care, delivered with excellence.</h1>
                <p>Welcome to Sunrise General Hospital, a modern healthcare center committed to delivering safe, patient-focused, and technology-driven medical services for every family we serve.</p>
                <div class="about-actions mt-3">
                    <a class="btn btn-primary" href="index.php">Book a Visit</a>
                    <a class="btn btn-outline-primary" href="#contact">Contact Us</a>
                </div>
                <div class="about-highlights mt-4">
                    <div class="about-highlight">
                        <strong>24/7</strong>
                        <span>Emergency Care</span>
                    </div>
                    <div class="about-highlight">
                        <strong>50+</strong>
                        <span>Specialist Doctors</span>
                    </div>
                    <div class="about-highlight">
                        <strong>15+</strong>
                        <span>Specialized Departments</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=900&q=80" alt="Hospital staff assisting a patient" class="about-image">
            </div>
        </div>
    </section>

    <section class="about-grid">
        <div class="card about-card">
            <div class="section-head">
                <h2>Our Mission</h2>
                <i class="bi bi-bullseye"></i>
            </div>
            <p>To provide accessible, compassionate, and high-quality healthcare services through innovation, integrity, and patient-centered care.</p>
        </div>
        <div class="card about-card">
            <div class="section-head">
                <h2>Our Vision</h2>
                <i class="bi bi-eye"></i>
            </div>
            <p>To become the region's trusted healthcare partner, known for excellence in treatment, education, and community wellness.</p>
        </div>
        <div class="card about-card">
            <div class="section-head">
                <h2>Our History</h2>
                <i class="bi bi-clock-history"></i>
            </div>
            <p>Founded in 2008, Sunrise General Hospital has grown from a small community clinic into a full-scale multi-specialty medical facility serving thousands of patients each year.</p>
        </div>
    </section>

    <section class="card about-card">
        <div class="section-head">
            <h2>Our Services</h2>
            <p>Modern care for every stage of life.</p>
        </div>
        <div class="service-grid">
            <div class="service-card">
                <i class="bi bi-hospital"></i>
                <h3>Emergency Care</h3>
                <p>Rapid response units, trauma support, and round-the-clock emergency treatment.</p>
            </div>
            <div class="service-card">
                <i class="bi bi-heart-pulse"></i>
                <h3>Cardiology</h3>
                <p>Diagnostics, monitoring, and advanced cardiac treatment plans.</p>
            </div>
            <div class="service-card">
                <i class="bi bi-bandaid"></i>
                <h3>General Medicine</h3>
                <p>Wellness checks, chronic disease management, and preventive care.</p>
            </div>
            <div class="service-card">
                <i class="bi bi-clipboard2-pulse"></i>
                <h3>Diagnostics</h3>
                <p>Lab tests, imaging, pathology, and specialist referrals in one place.</p>
            </div>
            <div class="service-card">
                <i class="bi bi-person-arms-up"></i>
                <h3>Pediatrics</h3>
                <p>Gentle, evidence-based care for newborns, children, and adolescents.</p>
            </div>
            <div class="service-card">
                <i class="bi bi-capsule"></i>
                <h3>Pharmacy</h3>
                <p>Prescription fulfillment, medicine counseling, and home delivery options.</p>
            </div>
        </div>
    </section>

    <section class="card about-card">
        <div class="section-head">
            <h2>Departments</h2>
            <p>Specialized teams working together for complete care.</p>
        </div>
        <div class="department-grid">
            <div class="department-item"><i class="bi bi-heart"></i> Cardiology</div>
            <div class="department-item"><i class="bi bi-baby"></i> Pediatrics</div>
            <div class="department-item"><i class="bi bi-brain"></i> Neurology</div>
            <div class="department-item"><i class="bi bi-body-text"></i> Orthopedics</div>
            <div class="department-item"><i class="bi bi-eyedropper"></i> Oncology</div>
            <div class="department-item"><i class="bi bi-lungs"></i> Pulmonology</div>
            <div class="department-item"><i class="bi bi-gender-female"></i> Obstetrics & Gynecology</div>
            <div class="department-item"><i class="bi bi-clipboard2-check"></i> Intensive Care Unit</div>
        </div>
    </section>

    <section class="card about-card">
        <div class="section-head">
            <h2>Facilities</h2>
            <p>Comfort, safety, and convenience for patients and visitors.</p>
        </div>
        <div class="facility-grid">
            <div class="facility-item">
                <i class="bi bi-wifi"></i>
                <h3>Free Wi-Fi</h3>
                <p>Stay connected throughout your visit.</p>
            </div>
            <div class="facility-item">
                <i class="bi bi-car-front"></i>
                <h3>Parking</h3>
                <p>Ample space for visitors and patients.</p>
            </div>
            <div class="facility-item">
                <i class="bi bi-cup-hot"></i>
                <h3>Patient Lounge</h3>
                <p>Comfortable seating and refreshments available.</p>
            </div>
            <div class="facility-item">
                <i class="bi bi-shield-check"></i>
                <h3>Infection Control</h3>
                <p>Strict cleanliness standards across all units.</p>
            </div>
        </div>
    </section>

    <section class="card about-card">
        <div class="section-head">
            <h2>Emergency Services</h2>
            <p>Available any time, day or night.</p>
        </div>
        <div class="contact-grid">
            <div class="contact-card">
                <h3>Call Emergency</h3>
                <a href="tel:+15551234567"><i class="bi bi-telephone"></i> +1 (555) 123-4567</a>
                <p>Ambulance, trauma support, and ICU coordination are available 24/7.</p>
            </div>
            <div class="contact-card" id="contact">
                <h3>Visit Our Hospital</h3>
                <p><i class="bi bi-geo-alt"></i> 123 Health Avenue, Downtown District</p>
                <p><i class="bi bi-envelope"></i> info@sunrisehospital.org</p>
                <p><i class="bi bi-clock"></i> Open 24 Hours • 7 Days a Week</p>
            </div>
        </div>
    </section>

    <section class="card about-card map-card">
        <div class="section-head">
            <h2>Find Us</h2>
            <p>Conveniently located in the city center.</p>
        </div>
        <iframe src="https://www.google.com/maps?q=123%20Health%20Avenue%20Downtown%20District&output=embed" title="Hospital location map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </section>
</div>

<?php include 'footer.php'; ?>
