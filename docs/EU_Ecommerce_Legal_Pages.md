
# EU E‑Commerce Legal Pages Blueprint (3‑Page Setup)

This file describes a concise **three‑page legal stack** that keeps an online shop compliant with **EU consumer‑, data‑ and e‑commerce law** as of **August 2025**.  

---

## 1. **Legal Notice & Company Info (Imprint)**  — `/legal`

| Block | Details you must include |
|-------|--------------------------|
| **Merchant identity** | • Full legal name & legal form (`<ACME S.r.l.>`)<br>• Registered address<br>• Company / trade‑register number<br>• EU VAT ID |
| **Rapid contact** | • Contact e‑mail (monitored daily)<br>• Phone **or** live chat for “direct & effective” communication |
| **Regulatory data** (if applicable) | • Licensing/supervisory body & licence number (for regulated trades)<br>• Environmental registry IDs (WEEE, packaging, batteries) |
| **ADR statement** | • Whether you volunteer to use Alternative Dispute Resolution<br>• Link to your chosen ADR body (National Consumer Ombudsman, etc.)<br>• *The EU Online Dispute Resolution (ODR) platform link is **no longer required** after 20 July 2025.* |

---

## 2. Shipping & Returns — `/shipping-returns`

This page merges delivery logistics with the **EU 14‑day Right of Withdrawal** into one consumer‑friendly hub.

### 2.1 Shipping Policy

| Topic | Minimum EU compliance | Recommended best practice |
|-------|----------------------|---------------------------|
| **Destinations** | List Member States you ship to. State if you refuse PO boxes or remote territories. | Provide ISO country codes, estimated customs delays for non‑EU, and a Brexit note for UK. |
| **Carriers & Methods** | Name default carrier(s); disclose any delivery‑partner selection rights you reserve. | Offer tracked and express tiers; show live rates via API. |
| **Costs** | Show final cost incl. VAT *before* checkout. Indicate any free‑shipping thresholds. | Use a table per country/weight bracket. |
| **Dispatch & Delivery Window** | Give a *concrete* timeframe (e.g. “1‑2 business days dispatch, 3‑5 days delivery”). | Show a countdown timer to today’s daily cut‑off (e.g. “Order in the next 2 h 15 m to ship today”). |
| **Risk Transfer** | State that risk passes to consumer at delivery (Art. 20 CRD). | For high‑value items, require signature and insurance. |
| **Partial Shipments** | Explain conditions (no extra fees) and rights to cancel remaining items. | Allow the customer to opt‑out of partial shipments in account settings. |
| **Missing / Damaged Parcels** | Outline notification deadline and evidence needed. | Provide a self‑service claim form with photo upload. |

### 2.2 Returns & Right of Withdrawal

1. **Eligibility window** – 14 calendar days from delivery.  
2. **How to withdraw** – provide (a) downloadable EU Model Withdrawal Form and (b) one‑click “Return” button in account.  
3. **Return shipping costs**  
   * Consumer bears cost **unless** you offer “free returns”—state clearly.  
   * Supply prepaid label if free.  
4. **Condition of goods** – “unused and in original packaging”; reasonable handling allowed for inspection.  
5. **Refund timing & method** – within 14 days of notice, **but** you may withhold until goods received / evidence of return.  
6. **Partial refunds** – policy for diminished value due to handling beyond inspection.  
7. **Exempt items** – bespoke goods, sealed hygiene/health items once unsealed, perishable goods, digital content after download.  
8. **Exchange option** – describe workflow if you allow size/colour swap.  
9. **Return address / RMA** – full postal address plus RMA number format.  
10. **ADR link** – point to national ADR body for unresolved disputes.

*Last updated: 2025-08-05*

---

## 3. Terms of Sale & Warranty — `/terms`

A legally binding contract covering order flow, payment, liability and statutory warranty.

### 3.1 Contract Formation

| Step | Mandatory details |
|------|-------------------|
| **Offer & Acceptance** | Web display ≠ binding offer. Contract concludes when you email an **“Order Confirmation”**. |
| **Languages** | Declare supported languages and which version prevails. |
| **Technical steps** | Basket → Checkout → Review screen (“Confirm Order”) → Confirmation e‑mail. |
| **Error‑correction** | “Edit” links or browser back button on Review screen. |
| **Contract archive** | PDF copy sent via email and stored in user account for 12 months. |

### 3.2 Prices & Payment

* Prices include VAT and eco‑fees; shipping shown separately.  
* Accepted methods: Visa, Mastercard, SEPA Direct Debit, PayPal, Apple Pay, Klarna Pay‑Later.  
* Currency conversion fees borne by customer.  
* Promotional codes: single‑use, non‑stackable, no cash value.

### 3.3 Delivery Conditions

* Title & risk pass on delivery.  
* Failure to deliver within additional 14‑day grace gives right to cancel for full refund.  
* We may hold mixed orders until all items available **unless** customer opts for split delivery.

### 3.4 Statutory Warranty (Legal Guarantee of Conformity)

| Aspect | EU minimum | Your implementation |
|--------|------------|---------------------|
| **Duration** | 2 years from delivery (5 yrs for real‑estate fixtures in some MS). | 2 years across EU. |
| **Burden of proof** | Presumed non‑conformity if defect appears within **1 year** (can extend under national law). | We voluntarily extend to full 2 years. |
| **Remedies order** | Repair → Replacement → Price reduction → Refund. | Customer can choose between repair & replacement unless impossible/disproportionate. |
| **Time to remedy** | “Within a reasonable period” & without significant inconvenience. | Target: ship replacement within 5 business days of approval. |

### 3.5 Commercial Guarantee (if offered)

*Free 3‑year extended warranty on electronics.*

* Guarantor: **ACME S.r.l.**, Via Roma 10, 00100 Roma, Italia.  
* Scope: manufacturing defects, battery >80% capacity.  
* Claim route: online form → prepaid return label → replacement/refurb unit.  
* Commercial guarantee is **in addition to** statutory rights.

### 3.6 Liability & Force Majeure

* Unlimited liability for death, personal injury, fraud.  
* Otherwise limited to foreseeable damages at contract conclusion.  
* No liability for delays caused by events beyond reasonable control (natural disasters, strikes, cyber‑attacks).

### 3.7 Governing Law & Jurisdiction

* Contracts governed by Italian law; mandatory consumer protections of the customer’s Member State remain unaffected.  
* EU ODR platform link removed July 20 2025; ADR clause retained (see Shipping & Returns).

---

## 4. **Privacy & Cookie Policy**  — `/privacy`

Combines GDPR transparency (Arts. 12‑14) and ePrivacy Directive cookie information into one approachable notice.

### 4.1 Data‑controller basics
* Legal name & address  
* Data‑Protection Officer contact (if appointed)

### 4.2 What data, why & for how long
| Category | Example items | Purpose & GDPR legal basis | Retention |
|----------|--------------|----------------------------|-----------|
| Account data | Name, e‑mail, password hash | Contract (Art. 6 b) | Until account deletion + 6 yrs |
| Order data | Address, items, tax ID | Contract / Legal obligation (tax) | 10 yrs (tax) |
| Marketing | Newsletter opt‑in, click logs | Consent (Art. 6 a) | Until withdrawal |
| Technical logs | IP, device IDs | Legitimate interest (fraud; Art. 6 f) | 24 mths |

### 4.3 Recipients & transfers
Logistics partners, payment gateways, cloud hosting, analytics SaaS. List third‑country transfers & Art. 46 safeguards (SCCs, adequacy).

### 4.4 User rights
Access, rectification, erasure, restriction, portability, objection, lodge complaint with Supervisory Authority.

### 4.5 Cookies & tracking
* **Consent banner**: prior opt‑in for any non‑essential cookies (analytics, ads).  
* **Cookie table** (name, purpose, provider, expiry).  
* Withdrawal mechanism: link “Cookie Settings” in footer.

### 4.6 Automated decision‑making / profiling
State logic, significance & consequences if you run credit‑scoring, dynamic pricing etc.

*Last updated: 2025-08-05*  
