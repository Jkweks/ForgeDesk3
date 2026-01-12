# ForgeDesk3 Testing Checklist

## Pre-Testing Setup
1. Clear browser cache (Ctrl+Shift+R / Cmd+Shift+R)
2. Open browser console to watch for errors
3. Log in with default credentials:
   - Email: admin@forgedesk.local
   - Password: password

---

## ✅ Dashboard Testing (`/`)

### Statistics Cards
- [ ] Total Products count displays
- [ ] Low Stock count displays
- [ ] Critical Stock count displays
- [ ] Total Value displays

### Product List
- [ ] Products table loads with data
- [ ] Search works
- [ ] Category filter works
- [ ] Status filter works
- [ ] Export button downloads CSV with authentication

### Product Modal
- [ ] View product opens modal with details
- [ ] Edit product saves changes
- [ ] Locations tab shows locations
- [ ] Activity tab shows transactions
- [ ] Configurator tab shows BOM (if applicable)
- [ ] Activity export downloads CSV

---

## ✅ Categories Testing (`/categories`)

### Categories View
- [ ] Category tree/list loads
- [ ] Statistics cards display
- [ ] Add category modal works
- [ ] Edit category works
- [ ] Delete category works (with confirmation)
- [ ] Parent category dropdown populates
- [ ] Product count displays per category

---

## ✅ Suppliers Testing (`/suppliers`)

### Suppliers View
- [ ] Supplier list loads
- [ ] Statistics cards display
- [ ] Add supplier modal works
- [ ] Edit supplier works
- [ ] View supplier details shows products
- [ ] Contact information displays
- [ ] Search works

---

## ✅ Operations > Purchase Orders (`/purchase-orders`)

### Statistics Cards
- [ ] Total Orders count displays
- [ ] Open Orders count displays
- [ ] Pending Value displays
- [ ] Total Value displays

### PO List
- [ ] PO table loads
- [ ] Status filter works
- [ ] Supplier filter populates and works
- [ ] Search works

### Create PO Workflow
- [ ] Click "Create Purchase Order"
- [ ] Supplier dropdown populates
- [ ] Click "Add Item" button
- [ ] Product dropdown populates in line item
- [ ] Unit cost auto-fills from product
- [ ] Quantity changes update line total
- [ ] Total amount updates automatically
- [ ] Create PO saves successfully
- [ ] Shows success notification

### PO Details
- [ ] View PO shows correct details
- [ ] Line items display with progress bars
- [ ] Submit button changes status to "Submitted"
- [ ] Approve button changes status to "Approved"

### Material Receiving
- [ ] "Receive Materials" button appears on approved POs
- [ ] Receive modal shows unreceived items
- [ ] Quantity defaults to remaining quantity
- [ ] Can assign location per item
- [ ] Receive creates inventory transaction
- [ ] PO progress updates to "Partially Received" or "Received"
- [ ] Inventory quantity increases

---

## ✅ Operations > Cycle Counting (`/cycle-counting`)

### Statistics Cards
- [ ] Total Sessions count displays
- [ ] Active Sessions count displays
- [ ] Items Counted (Month) displays
- [ ] Accuracy % displays

### Create Session
- [ ] Click "Create Count Session"
- [ ] Location field accepts text input
- [ ] Category dropdown populates
- [ ] Product multi-select populates
- [ ] Create session works

### Count Entry
- [ ] Click count icon on active session
- [ ] Product list shows with system quantities
- [ ] Enter counted quantity
- [ ] Variance calculates automatically
- [ ] Color coding works (green=match, red=variance)
- [ ] Notes field works

### Variance Review
- [ ] "Review Variances" button shows variance summary
- [ ] Positive/negative variance displays
- [ ] Select variances to approve
- [ ] "Approve Selected" creates inventory adjustments
- [ ] Inventory quantities update

### Complete Session
- [ ] Complete button validates all counted
- [ ] Completes successfully
- [ ] Accuracy percentage calculated

---

## ✅ Reports Testing (`/reports`)

### Report Navigation
- [ ] Six report type buttons display
- [ ] Click each button loads corresponding report

### Low Stock Report
- [ ] Statistics cards display
- [ ] Low stock items table populates
- [ ] Critical items show in red
- [ ] Value at risk calculates
- [ ] Export button downloads CSV

### Committed Parts Report
- [ ] Shows products with reservations
- [ ] Reservation details display
- [ ] Statistics accurate

### Stock Velocity Analysis
- [ ] Days dropdown works (30/60/90/180)
- [ ] Fast/Medium/Slow movers classified
- [ ] Turnover % calculates
- [ ] Export works

### Reorder Recommendations
- [ ] Shortage calculations correct
- [ ] Recommended quantities display
- [ ] Critical items highlighted
- [ ] Export works

### Obsolete Inventory
- [ ] Inactive items show
- [ ] Days since last use displays
- [ ] BOM usage indicated
- [ ] Export works

### Usage Analytics
- [ ] By date table populates
- [ ] By category table populates
- [ ] Summary statistics correct

---

## ✅ Maintenance Hub (`/maintenance`)

### Dashboard
- [ ] Machine count displays
- [ ] Active tasks count displays
- [ ] Overdue tasks count displays

### Machines Tab
- [ ] Machine list loads
- [ ] Add machine works
- [ ] Edit machine works
- [ ] View tasks per machine

### Assets Tab
- [ ] Asset list loads
- [ ] Add asset works
- [ ] Edit asset works

### Tasks Tab
- [ ] Task list loads
- [ ] Add task works
- [ ] Priority color coding works
- [ ] Due date highlighting works

### Service Log Tab
- [ ] Records list loads
- [ ] Add record works
- [ ] Attach to machine works

---

## Common Issues to Check

### Authentication
- [ ] Login works
- [ ] Logout works
- [ ] Auth token persists on page refresh
- [ ] Unauthorized requests redirect to login

### API Errors
- [ ] No "apiCall is not defined" errors
- [ ] No "showNotification is not defined" errors
- [ ] No type errors on response objects
- [ ] No 401 Unauthorized errors

### Export Functionality
- [ ] All CSV exports include authentication
- [ ] Files download with proper names
- [ ] CSV format is valid

### UI/UX
- [ ] Modals open and close properly
- [ ] Success notifications display
- [ ] Error notifications display
- [ ] Loading indicators show during API calls
- [ ] Buttons disabled during operations
- [ ] Forms validate properly

### Navigation
- [ ] All menu items work
- [ ] Active page highlights in nav
- [ ] Dropdowns work
- [ ] Back button doesn't break app

---

## Performance Checks

- [ ] Pages load within 2 seconds
- [ ] No console errors
- [ ] No console warnings (except LastPass browser extension)
- [ ] API calls complete quickly
- [ ] Tables render smoothly with 100+ items

---

## Browser Compatibility

Test in:
- [ ] Chrome/Edge
- [ ] Firefox
- [ ] Safari (if available)

---

## Mobile Responsiveness

- [ ] Navigation collapses properly
- [ ] Tables scroll horizontally
- [ ] Modals display correctly
- [ ] Touch interactions work

---

## Data Integrity

- [ ] Creating records doesn't duplicate
- [ ] Editing preserves relationships
- [ ] Deleting shows confirmation
- [ ] Soft deletes work where applicable
- [ ] Transaction history maintained

---

## Notes

**Known Non-Issues:**
- LastPass browser extension warnings (ignore these)
- CSRF token refreshes automatically

**Report Issues:**
Note any failures with:
1. What you were doing
2. What you expected
3. What actually happened
4. Browser console errors (if any)
