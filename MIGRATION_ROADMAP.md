# ForgeDesk2 to ForgeDesk3 Migration Roadmap

## Executive Summary

This document outlines the comprehensive plan to migrate features from ForgeDesk2 (PHP/PostgreSQL) to ForgeDesk3 (Laravel/Tabler) while maintaining the new design system and ensuring no features are lost unless explicitly requested.

**Status**: In Progress
**Last Updated**: 2026-01-06

---

## Completed Items

### ✅ Design System Integration
- Tabler UI framework fully integrated
- Theme customization system (color picker, fonts, dark/light mode)
- Responsive navigation and layout
- Status badge text contrast fixed (using `text-bg-*` classes)

### ✅ Basic Infrastructure
- Laravel application structure
- Authentication system (login/logout with bearer tokens)
- API structure (`/api/v1` endpoints)
- Basic dashboard layout

### ✅ Core Inventory Features (Basic)
- Dashboard with key metrics
- Inventory table with search
- Add product modal with validation
- Tab filtering (All, Low Stock, Critical)

---

## Feature Gap Analysis

### Current ForgeDesk3 Features
- Basic inventory CRUD
- Simple product fields (SKU, description, location, quantities, supplier)
- Stock status indicators
- Export functionality (stub)
- Theme customization

### Missing Features from ForgeDesk2

#### 🔴 Critical - Core Business Logic
1. **Location Hierarchy System**
   - Multiple storage locations per item
   - Quantity allocation across locations
   - Location-based stock tracking
   - Location discrepancy detection

2. **Job Reservations & Commitments**
   - Reserve inventory for jobs
   - Committed quantity tracking
   - Active reservations count
   - Job-to-inventory linking

3. **Advanced Inventory Fields**
   - Finish codes (BL, C2, DB, 0R, etc.)
   - Pack size and pack quantity management
   - Purchase UOM vs Stock UOM
   - Minimum order quantities
   - Order multiples
   - Safety stock calculations
   - Average daily use tracking

#### 🟡 Important - Operational Features
4. **Categories & Organization**
   - Category system
   - Subcategory groupings
   - Systems classification
   - Category-based filtering

5. **Supplier Management**
   - Supplier directory
   - Supplier contacts auto-population
   - Supplier SKU tracking
   - Lead time management per supplier
   - Purchase order integration

6. **Activity & Audit Trail**
   - Transaction history
   - Receipt tracking
   - Cycle count records
   - Stock adjustments log
   - Timestamp and user tracking

7. **Configurator System**
   - Configurator availability toggle
   - Type and use path selectors
   - Part dimensions (height/depth)
   - Required parts list (BOM)
   - Quantity and finish policy options

#### 🟢 Enhancement - Nice to Have
8. **Advanced Features**
   - Cycle counting workflow
   - EZ Estimate analysis
   - Discontinued item handling
   - Low stock alerts system
   - Reorder point calculations

9. **Reporting & Analytics**
   - CSV export (full implementation)
   - Low & Critical inventory reports
   - Committed parts reporting
   - Usage analytics

---

## Database Schema Comparison

### ForgeDesk2 Schema (PostgreSQL)
```sql
inventory_items (
  id, item, sku, part_number, finish, location,
  stock, supplier, supplier_id, supplier_sku, supplier_contact,
  reorder_point, lead_time_days, on_order_qty,
  average_daily_use, safety_stock, status,
  pack_size, purchase_uom, stock_uom,
  min_order_qty, order_multiple,
  committed_qty, active_reservations
)
```

### ForgeDesk3 Current Schema (Laravel)
```sql
products (
  id, sku, description, long_description,
  category, location, unit_cost, unit_price,
  quantity_on_hand, minimum_quantity, maximum_quantity,
  unit_of_measure, supplier, supplier_sku,
  lead_time_days, is_active
)
```

### Required Schema Additions
- `part_number` field
- `finish` field
- `pack_size`, `purchase_uom`, `stock_uom` fields
- `committed_qty`, `available_qty` (calculated)
- `reorder_point`, `safety_stock` fields
- `average_daily_use` field
- `on_order_qty` field
- `supplier_id` foreign key
- `categories` table
- `suppliers` table
- `inventory_locations` table (one-to-many)
- `job_reservations` table
- `inventory_transactions` table
- `configurator_settings` table
- `required_parts` table (BOM)

---

## Migration Roadmap - Phased Approach

### Phase 1: Data Model Enhancement (Priority: Critical)
**Timeline**: Week 1-2

1. **Database Migrations**
   - Create suppliers table
   - Create categories table
   - Create inventory_locations table (linked to products)
   - Create job_reservations table
   - Create inventory_transactions table
   - Add missing fields to products table
   - Set up foreign keys and relationships

2. **Model Updates**
   - Create Supplier model with relationships
   - Create Category model
   - Create InventoryLocation model
   - Create JobReservation model
   - Create InventoryTransaction model
   - Update Product model with new fields and relationships

3. **API Endpoints - Data Structure**
   - Update existing product endpoints to include new fields
   - Create supplier CRUD endpoints
   - Create category endpoints
   - Create location management endpoints

**Deliverables**:
- Complete database schema matching ForgeDesk2 capabilities
- Laravel models with proper relationships
- Migration files for all schema changes

---

### Phase 2: Location Management (Priority: Critical)
**Timeline**: Week 3

1. **Multi-Location Support**
   - UI for adding multiple locations per product
   - Location-based quantity allocation
   - Location picker component
   - Location hierarchy display

2. **Stock Distribution**
   - Visual representation of stock across locations
   - Location-based availability calculations
   - Location discrepancy alerts
   - Bulk location operations

**Deliverables**:
- Products can have inventory distributed across multiple locations
- Location management UI in product modal
- Location-based stock queries

---

### Phase 3: Job Reservations & Commitments (Priority: Critical)
**Timeline**: Week 4

1. **Reservation System**
   - Create reservation interface
   - Job-based reservation creation
   - Committed quantity calculations
   - Available-to-promise (ATP) logic

2. **Reservation Management**
   - View active reservations per product
   - Reservation history
   - Release/cancel reservations
   - Job linking

**Deliverables**:
- Complete reservation system
- ATP calculations on dashboard
- Reservation management interface

---

### Phase 4: Enhanced Product Details (Priority: Important)
**Timeline**: Week 5-6

1. **Part Number & Finish System**
   - Part number field
   - Finish dropdown (configurable)
   - Auto-generated SKU from part_number + finish
   - Finish-based product variations

2. **Pack & UOM Management**
   - Pack size configuration
   - Purchase UOM vs Stock UOM
   - Minimum order quantities
   - Order multiples
   - Unit conversion logic

3. **Advanced Stock Fields**
   - Safety stock field
   - Average daily use calculation
   - On-order quantity tracking
   - Reorder point logic

**Deliverables**:
- Complete product detail parity with ForgeDesk2
- Enhanced product creation/edit modal
- Automatic calculations and validations

---

### Phase 5: Categories & Organization (Priority: Important)
**Timeline**: Week 7

1. **Category System**
   - Category management interface
   - Subcategory support
   - Systems classification
   - Hierarchical category display

2. **Category Integration**
   - Category selector in product modal
   - Category-based filtering
   - Category dashboard widgets
   - Bulk category operations

**Deliverables**:
- Complete category taxonomy
- Category-based navigation
- Category management tools

---

### Phase 6: Supplier Management (Priority: Important)
**Timeline**: Week 8

1. **Supplier Directory**
   - Supplier CRUD interface
   - Supplier contact information
   - Supplier-product relationships
   - Lead time per supplier

2. **Supplier Integration**
   - Supplier dropdown in product modal
   - Auto-populate supplier contacts
   - Supplier-based filtering and reporting
   - Multiple suppliers per product support

**Deliverables**:
- Complete supplier management system
- Supplier directory interface
- Supplier analytics

---

### Phase 7: Activity & Audit Trail (Priority: Important)
**Timeline**: Week 9

1. **Transaction History**
   - Transaction logging system
   - Transaction types (receipt, adjustment, cycle count)
   - Transaction detail modal
   - User and timestamp tracking

2. **Activity Tab**
   - Activity tab in product modal
   - Transaction history table
   - Filterable transaction log
   - Export transaction history

**Deliverables**:
- Complete audit trail
- Transaction history UI
- Activity tracking for all inventory operations

---

### Phase 8: Configurator System (Priority: Enhancement)
**Timeline**: Week 10-11

1. **Configurator Settings**
   - Configurator availability toggle
   - Type and use path selectors
   - Part dimensions (lz, ly)
   - Dimension-based calculations

2. **Required Parts (BOM)**
   - Required parts interface
   - Part search autocomplete
   - Quantity and finish policies
   - BOM explosion calculations

**Deliverables**:
- Complete configurator system
- BOM management interface
- Configurator-based product creation

---

### Phase 9: Advanced Operations (Priority: Enhancement)
**Timeline**: Week 12-13

1. **Cycle Counting**
   - Cycle count workflow
   - Count vs expected discrepancies
   - Cycle count scheduler
   - Count history and variance reporting

2. **Stock Analysis**
   - EZ Estimate integration
   - Reorder recommendations
   - Stock velocity analysis
   - Obsolete inventory detection

3. **Enhanced Reporting**
   - Low & Critical inventory reports
   - Committed parts dashboard
   - Usage analytics
   - Custom report builder

**Deliverables**:
- Complete operational tools
- Advanced reporting suite
- Analytics dashboard

---

### Phase 10: Polish & Optimization (Priority: Enhancement)
**Timeline**: Week 14

1. **Performance Optimization**
   - Database query optimization
   - Eager loading for relationships
   - Caching strategy
   - API response time optimization

2. **UX Improvements**
   - Keyboard shortcuts
   - Bulk operations interface
   - Advanced search and filtering
   - Customizable dashboard widgets

3. **Testing & Documentation**
   - Feature testing
   - User acceptance testing
   - Documentation updates
   - Training materials

**Deliverables**:
- Optimized application performance
- Comprehensive documentation
- Production-ready system

---

## Technical Considerations

### API Design Principles
- RESTful endpoints for all resources
- Consistent error handling
- Proper HTTP status codes
- API versioning (`/api/v1`)
- Request validation using Laravel Form Requests
- Paginated responses for lists

### Frontend Patterns
- Maintain Tabler design consistency
- Progressive enhancement
- Responsive design for mobile/tablet
- Accessible components (ARIA labels)
- Loading states and error feedback
- Optimistic UI updates where appropriate

### Data Migration
- Export scripts from ForgeDesk2 PostgreSQL
- Data transformation layer
- Import scripts for ForgeDesk3 Laravel
- Data validation and integrity checks
- Rollback procedures

### Testing Strategy
- Unit tests for models and business logic
- Feature tests for API endpoints
- Browser tests for critical user flows
- Performance testing for large datasets
- Regression testing for each phase

---

## Risk Mitigation

### Data Loss Prevention
- Comprehensive backup strategy
- Dual-running period (ForgeDesk2 + ForgeDesk3)
- Data validation at every migration step
- No deletion of original ForgeDesk2 data until verified

### Feature Completeness
- ✅ This roadmap ensures all ForgeDesk2 features are documented
- Phase-by-phase checklist verification
- User acceptance testing at each phase
- Feature flags for gradual rollout

### User Adoption
- Training sessions for new interface
- Side-by-side comparison documentation
- Gradual migration path
- Rollback capability if needed

---

## Success Metrics

### Feature Parity
- [ ] 100% of ForgeDesk2 database fields supported
- [ ] 100% of ForgeDesk2 UI screens recreated
- [ ] 100% of ForgeDesk2 workflows supported
- [ ] Zero feature regression

### Data Integrity
- [ ] 100% of data successfully migrated
- [ ] All relationships preserved
- [ ] Historical data intact
- [ ] Audit trail complete

### Performance
- [ ] Dashboard loads < 2 seconds
- [ ] API response times < 500ms
- [ ] Search results < 1 second
- [ ] Supports 10,000+ inventory items

### User Experience
- [ ] Modern, intuitive interface
- [ ] Mobile responsive
- [ ] Accessibility compliant (WCAG 2.1 AA)
- [ ] User satisfaction > 4/5

---

## Next Steps

1. **Immediate Actions**
   - Review and approve this roadmap
   - Prioritize phases based on business needs
   - Begin Phase 1 database migrations
   - Set up development/staging environments

2. **Team Requirements**
   - Backend developer for API and database work
   - Frontend developer for UI implementation
   - QA for testing
   - Product owner for feature validation

3. **Decision Points**
   - Approve timeline and phase priorities
   - Confirm data migration approach
   - Define success criteria for each phase
   - Establish testing procedures

---

## Appendix

### ForgeDesk2 Reference URLs
- Repository: https://github.com/jkweks/forgedesk2
- Main Dashboard: `/public/index.php`
- Inventory Manager: `/public/inventory.php`
- Data Layer: `/app/data/inventory.php`

### Key Design Decisions
1. **No Feature Removal**: All features from ForgeDesk2 will be included unless explicitly requested for removal
2. **Design System**: Maintain Tabler UI consistency throughout
3. **API First**: Build robust API layer for future extensibility
4. **Progressive Enhancement**: Deliver in functional phases
5. **Data Integrity**: Never compromise data accuracy or completeness

### Change Log
- 2026-01-06: Initial roadmap created
- 2026-01-06: Fixed status badge text contrast (bg-* → text-bg-*)
