# Technical Implementation Details

## File-by-File Changes

---

## 1. login.php
### HTML Structure Changes:
```html
<!-- Added wrapper container for centering -->
<div class="container">
  <div class="box">
    <!-- Form content -->
  </div>
</div>
```

### CSS Improvements:
- Added `.container` with flexbox centering
- Responsive `.box` width (100% max 400px)
- Form groups with proper label styling
- Improved input styling with 1.5px borders
- Focus states with green border (#0077cc) and shadow
- Button hover effects with lift animation (transform: translateY(-2px))
- Responsive breakpoints at 768px and 480px

### New Form Group Structure:
```html
<div class="form-group">
  <label for="username">Username</label>
  <input type="text" id="username" name="username" ... required>
</div>
```

### Responsive Classes:
- @media (max-width: 768px) - Tablet adjustments
- @media (max-width: 480px) - Mobile adjustments

---

## 2. register.php
### Changes:
- Identical structure to login.php for consistency
- Same responsive breakpoints (768px, 480px)
- Added email input with type="email" for validation
- Maintained blue-green gradient background

### Form Fields:
1. Username (text input)
2. Email (email input) - built-in validation
3. Password (password input)

---

## 3. deposit.php
### Major Changes:

#### 1. Dynamic Bottle Entry System
```javascript
let bottleCount = 1;

function addBottleEntry() {
  bottleCount++;
  // Creates new bottle entry with:
  // - Customer name input
  // - Purchased from input (NEW)
  // - Bottle type select with custom option
  // - Quantity input
  // - Amount input
  // - Remove button
}

function removeBottleEntry(button) {
  // Validates minimum 1 entry
  // Auto-renumbers entries after removal
}
```

#### 2. HTML Form Structure
```html
<div id="bottlesContainer">
  <div class="bottle-entry"> <!-- Dynamically generated -->
    <div style="flex: 1;">
      <div class="form-row">
        <!-- Customer Name -->
      </div>
      <div class="form-row">
        <!-- Purchased From (NEW FIELD) -->
      </div>
      <div class="form-row">
        <!-- Bottle Type & Quantity -->
      </div>
      <div class="form-row">
        <!-- Amount -->
      </div>
    </div>
  </div>
</div>
```

#### 3. PHP Database Changes
```php
// Before: Single insert
INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, deposit_date) 
VALUES (?, ?, ?, ?, NOW())

// After: Includes purchased_from
INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, purchased_from, deposit_date) 
VALUES (?, ?, ?, ?, ?, NOW())

// Bind parameters updated to include purchased_from
$ins->bind_param("isssi", $user_id, $customer_name, $bottle_type, $purchased_from, $quantity);
```

#### 4. Menu Button Change
```html
<!-- Before -->
<button class="toggle-sidebar" onclick="toggleSidebar()">☰</button>

<!-- After -->
<button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button>
```

#### 5. Confirmation Dialog Enhancement
```javascript
function confirmDeposit(e){
  const entries = document.querySelectorAll('.bottle-entry');
  let totalQty = 0;
  entries.forEach(entry => {
    const qty = parseInt(entry.querySelector('input[name="quantity[]"]').value) || 0;
    totalQty += qty;
  });
  // Shows: "You are about to record X bottles across Y entry(ies). Continue?"
  return confirm(`You are about to record ${totalQty} bottles across ${entries.length} entry(ies). Continue?`);
}
```

---

## 4. index.php
### Change:
```html
<!-- Before -->
<button class="toggle-sidebar" onclick="toggleSidebar()">☰</button>

<!-- After -->
<button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button>
```

---

## 5. refund.php
### Change:
```html
<!-- Before -->
<div class="brand"><button class="toggle-sidebar" onclick="toggleSidebar()">☰</button>...

<!-- After -->
<div class="brand"><button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button>...
```

---

## 6. returns.php
### Change:
Same as refund.php - hamburger emoji replaced with "Menu"

---

## 7. stock_log.php
### Change:
Same as refund.php - hamburger emoji replaced with "Menu"

---

## 8. asset/style.css
### New CSS Classes Added:

```css
/* Enhanced Form Styling */
.form-row {
  display: flex;
  gap: 15px;
  margin-bottom: 15px;
  flex-wrap: wrap;
}

.form-row .col {
  flex: 1;
  min-width: 200px;
}

.form-row .col label {
  display: block;
  text-align: left;
  margin-bottom: 8px;
  color: #2d6a6a;
  font-weight: 600;
  font-size: 14px;
}

.form-row .col input,
.form-row .col select {
  width: 100%;
  padding: 11px 12px;
  border: 1.5px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-row .col input:focus,
.form-row .col select:focus {
  outline: none;
  border-color: #26a69a;
  box-shadow: 0 0 6px rgba(38, 166, 154, 0.15);
  background: #fafbfc;
}

.bottle-entry {
  position: relative;
  transition: all 0.3s ease;
}

.bottle-entry:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

/* Button Enhancements */
button {
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  font-family: 'Poppins', 'Segoe UI', sans-serif;
  font-size: 14px;
}

button.primary {
  background: #26a69a;
  color: white;
}

button.primary:hover {
  background: #2e7d7d;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(38, 166, 154, 0.25);
}

button.primary:active {
  transform: translateY(0);
}

button.ghost {
  background: #80cbc4;
  color: #004d40;
  border: 1px solid #80cbc4;
}

button.ghost:hover {
  background: #4db6ac;
  border-color: #4db6ac;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(128, 203, 196, 0.25);
}

button.ghost:active {
  transform: translateY(0);
}

/* Message Styling */
.notice {
  padding: 12px 16px;
  background: #e9fbf1;
  border-left: 4px solid #26a69a;
  border-radius: 6px;
  margin-bottom: 16px;
  color: #155724;
  font-weight: 500;
  font-size: 14px;
}

.error {
  padding: 12px 16px;
  background: #ffecec;
  border-left: 4px solid #ef5350;
  border-radius: 6px;
  margin-bottom: 16px;
  color: #c62828;
  font-weight: 500;
  font-size: 14px;
}

/* Toggle Sidebar */
.toggle-sidebar {
  background: none;
  border: none;
  font-size: 16px;
  cursor: pointer;
  color: #2d6a6a;
  font-weight: 600;
}

.toggle-sidebar:hover {
  color: #00796b;
}

/* Mobile Responsive Form */
@media (max-width: 768px) {
  .form-row {
    flex-direction: column;
    gap: 12px;
  }
  
  .form-row .col {
    min-width: 100%;
  }
  
  button {
    padding: 10px 14px;
    font-size: 13px;
  }
  
  .panel {
    padding: 15px;
  }
}
```

---

## Database Impact

### deposit table now uses:
```sql
INSERT INTO deposit (user_id, customer_name, bottle_type, quantity, purchased_from, deposit_date)
VALUES (?, ?, ?, ?, ?, NOW())
```

**Parameters:**
- user_id (int)
- customer_name (string) - Who purchased the bottle
- bottle_type (string) - Type of bottle
- quantity (int) - Number of bottles
- purchased_from (string) - Supplier/location
- deposit_date (datetime) - Auto-set to NOW()

### stock_log captures:
```sql
INSERT INTO stock_log (user_id, action_type, customer_name, bottle_type, quantity, amount, details)
VALUES (?, 'Deposit', ?, ?, ?, ?, ?)
```

**Details field format:**
- "Successfully recorded" (if no supplier)
- "Successfully recorded from [supplier_name]" (if supplier provided)

---

## JavaScript Functions Added

### In deposit.php:
1. **addBottleEntry()** - Dynamically adds new bottle entry form
2. **removeBottleEntry(button)** - Removes bottle entry with validation
3. **handleBottleTypeChange(select)** - Shows/hides custom bottle type input
4. **confirmDeposit(e)** - Validates and shows confirmation dialog

### Event Listeners:
- Change listener on all bottle_type selects
- Click handler on add/remove buttons
- Submit handler on form with validation

---

## Responsive Breakpoints

### Login & Register Pages:
- **Default (768px+):** 400px max-width, 40px padding
- **Tablet (600-768px):** 30px padding, 24px h2 font
- **Mobile (480px):** 25px padding, 22px h2 font, reduced input padding

### Deposit & Other Forms:
- **Desktop (768px+):** Side-by-side form columns
- **Mobile (<768px):** Stacked form columns, full width

---

## CSS Properties Modified

### Color Values:
- Primary: #26a69a (Teal)
- Dark: #2d6a6a (Dark teal)
- Light: #80cbc4 (Light teal)
- Success: #e9fbf1 (Light green)
- Error: #ffecec (Light red)

### Shadow Effects:
- Input focus: `box-shadow: 0 0 8px rgba(0, 119, 204, 0.3)`
- Button hover: `box-shadow: 0 4px 12px rgba(38, 166, 154, 0.25)`
- Bottle entry: `box-shadow: 0 2px 8px rgba(0,0,0,0.08)`

### Transitions:
- All: `transition: all 0.3s ease`
- Common duration: 0.3s
- Easing: ease (smooth acceleration/deceleration)

---

## Validation Rules

### Deposit Form:
1. Quantity > 0 for all entries
2. Amount >= 0 (cannot be negative)
3. Minimum 1 bottle entry required
4. At least one entry must have bottle type selected
5. Customer name recommended but optional

### Login/Register:
1. Username required
2. Password required
3. Email required (register only, with HTML5 validation)

---

## Performance Considerations

### JavaScript:
- Event delegation for dynamic elements
- Minimal DOM manipulation
- No external library dependencies
- Lightweight function calls

### CSS:
- CSS Grid and Flexbox for responsive layout
- Minimal use of calc()
- Optimized selectors
- Hardware-accelerated transforms (translateY)

### HTML:
- Semantic structure
- Minimal inline styles (only where necessary)
- Proper form structure with labels
- Accessibility attributes (id, for, required)

---

## Browser Support

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| Flexbox | ✅ | ✅ | ✅ | ✅ |
| CSS Grid | ✅ | ✅ | ✅ | ✅ |
| Transform | ✅ | ✅ | ✅ | ✅ |
| Box-shadow | ✅ | ✅ | ✅ | ✅ |
| Media Queries | ✅ | ✅ | ✅ | ✅ |
| Gradient | ✅ | ✅ | ✅ | ✅ |

---

## Future Optimization Opportunities

1. **Lazy Load Images** - For customer avatars
2. **Service Worker** - For offline support
3. **CSS Minification** - Reduce CSS file size
4. **JavaScript Bundle** - Combine and minify JS
5. **CDN Delivery** - Serve static assets from CDN
6. **Image Optimization** - WebP format support
7. **Caching Headers** - Improved browser caching

---

**Technical Documentation Version:** 1.0  
**Last Updated:** January 18, 2026  
**Developer:** System Enhancement Team
