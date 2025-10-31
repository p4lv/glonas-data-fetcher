# Changelog

All notable changes to Glonass Import API will be documented in this file.

## [1.0.7] - 2025-10-30

### Changed
- **Vehicle Data Synchronization Now Uses `/vehicles/getlastdata` Endpoint**
  - Updated `UpdateVehicleStatusMessageHandler` to use POST `/api/v3/vehicles/getlastdata`
  - More efficient than `/vehicles/find` - only fetches last data for existing vehicles
  - **Optimized batch processing**:
    - Batch size reduced from 100 to 25 vehicles (avoids API rate limiting)
    - Rate limit increased from 1 to 2 seconds between requests
    - Vehicles sorted by `status_checked_at` (oldest first) - prioritizes long-unchecked vehicles
  - Processes 12,139 vehicles in 486 batches (~17 minutes)
  - Each vehicle gets real-time GPS data: coordinates, speed, course, recordTime
  - Handles vehicles with no GPS data gracefully (null values)
  - Successfully tested without 403 Forbidden errors

### Fixed
- **getlastdata API Request Format**
  - Fixed request body format: API expects array of IDs `[800213974, 800255515]`
  - Previously was sending `{"vehicleIds": [...]}` which caused 400 errors
  - Now correctly sends plain array of integer IDs

### Added
- **New GPS Status: `no_data`**
  - Added `no_data` status for vehicles that exist in API but have no GPS data
  - Automatically set when API returns response but all GPS fields are null
  - Displayed with ⚠️ yellow/warning badge in UI
  - Added to GPS Status filter dropdown
  - Added to statistics dashboard (Online/Offline/No Data/Unknown/Total)
  - Applied when:
    - API returns no data at all for vehicle
    - API returns vehicle data but latitude, longitude, and recordTime are all null
  - Different from `unknown`: `no_data` means we checked and confirmed no GPS data available

- **Single Vehicle Refresh Feature on Web UI**
  - Added "Refresh" button on vehicle details page (e.g., `/vehicles/12087`)
  - Fetches latest GPS data from Glonass API in real-time (< 50ms)
  - Updates coordinates, speed, course, last position time, GPS status
  - Automatically sets status to `no_data` when API returns no GPS data
  - Shows success message with updated GPS status and speed
  - Handles vehicles without GPS data gracefully with warning message
  - Route: `POST /vehicles/{id}/refresh`
  - Added GPS Status badge to vehicle details page (Online/Offline/No Data/Unknown)
  - Added "Status Checked" timestamp display

- **API Client Methods**
  - `GlonassApiClient::getLastData()` - Fetch last data for multiple vehicles
  - `GlonassApiClient::getLastDataForVehicle()` - Fetch last data for single vehicle

- **Repository Methods**
  - `VehicleRepository::findAllOrderedByStatusCheck()` - Get vehicles sorted by status_checked_at (oldest first, NULL first)

- **Message Handler Methods**
  - `UpdateVehicleStatusMessageHandler::processBatchWithGetLastData()` - New batch processor
  - `UpdateVehicleStatusMessageHandler::updateVehicleDataFromGetLastData()` - Handle getlastdata response format

- **Controller Action**
  - `VehicleWebController::refresh()` - Refresh single vehicle data from web UI

- **Development Tools**
  - `TestGetlastdataCommand` - Test command for getlastdata endpoint

- **Configuration Constants**
  - `UpdateVehicleStatusMessageHandler::BATCH_SIZE` changed from 100 to 25
  - `GlonassApiClient::RATE_LIMIT_DELAY` changed from 1 to 2 seconds

### Technical Notes

**API Endpoint Correct Usage:**
```bash
# Correct format: plain array of external IDs
curl -X POST "https://regions.glonasssoft.ru/api/v3/vehicles/getlastdata" \
  -H "Content-Type: application/json" \
  -H "X-Auth: <TOKEN>" \
  -d "[800213974, 800255515]"
```

**Response Format:**
```json
[
  {
    "vehicleId": 800213974,
    "vehicleGuid": "83aee233-a3cb-4fca-8530-3ab725cd9618",
    "vehicleNumber": "Маяк хард",
    "receiveTime": "2025-05-21T07:49:14.0996687Z",
    "recordTime": "2025-05-21T07:49:13Z",
    "state": 1,
    "speed": 0,
    "course": 235,
    "latitude": 43.2453,
    "longitude": 76.8505,
    "address": "улица Отарская, Алматы, Казахстан",
    "geozones": []
  }
]
```

**Performance:**
- 12,139 vehicles processed in ~2 minutes
- 122 API calls (100 vehicles per batch)
- Rate limiting: 1 second between requests
- Much faster than `/vehicles/find` which returns ALL vehicles

**Automation:**
```bash
# Sync every 2 hours via cron
0 */2 * * * cd /path && php bin/console app:sync:vehicle-data --async
```

## [1.0.6] - 2025-10-29

### Added
- **Vehicle Data Synchronization Command**
  - `app:sync:vehicle-data` - New command to synchronize vehicle data from API
    - Alias for `app:update:vehicle-status` for clearer naming
    - Synchronizes GPS coordinates, speed, course, and timestamps
    - Auto-updates GPS status (online/offline/unknown) based on data freshness
    - Supports `--async` flag for background processing
    - Supports `--vehicle-id=ID` for single vehicle updates
    - Uses batch processing (100 vehicles per batch)
  - Enhanced messaging with processing time estimates
  - Clear documentation of API endpoint used (POST /api/v3/vehicles/find)

### Changed
- Command descriptions improved for better clarity
- Success messages now include technical details about data source

### Technical Notes
**API Endpoint Research:**
- Investigated POST /api/v3/vehicles/getlastdata endpoint
- Initial attempts returned 400 validation errors due to incorrect request format
- Solution found: API expects plain array of IDs, not wrapped in object

**Data Synchronization:**
- `/vehicles/find` returns complete vehicle list with current data
- Data is real-time or near-real-time from Glonass servers
- Rate limiting: 1 second between API calls (automatically handled)
- ~12,000 vehicles processed in 20-30 seconds

**Automation:**
```bash
# Sync every 2 hours via cron
0 */2 * * * cd /path && php bin/console app:sync:vehicle-data --async
```

## [1.0.5] - 2025-10-29

### Added
- **GPS Status Tracking System**
  - New fields in Vehicle entity:
    - `gpsStatus` (online/offline/unknown) - Current GPS status
    - `lastServerDataTime` - Copy of last position time for clarity
    - `connectionStatus` (connected/disconnected/no_data) - Terminal connection status
    - `statusCheckedAt` - Timestamp of last status check
  - `Vehicle::updateGpsStatus()` - Automatically determine GPS status based on data freshness
    - Online: Last position within 2 hours
    - Offline: Last position older than 2 hours
    - Unknown: No position data available
  - `Vehicle::isGpsOnline()` - Quick check if GPS is online
  - `Vehicle::hasGpsData()` - Check if vehicle has any GPS data

- **Status Update Command & Automation**
  - `app:update:vehicle-status` - Console command to update GPS statuses
    - `--async` flag for background processing
    - `--vehicle-id` to update specific vehicle
    - Uses batch processing for all vehicles
  - `UpdateVehicleStatusMessage` & Handler for Messenger integration
  - Suitable for cron automation (every 2 hours recommended)

- **UI Enhancements for GPS Status**
  - GPS Status Overview dashboard with statistics:
    - Online count (green badge)
    - Offline count (red badge)
    - Unknown count (gray badge)
    - Total vehicles count
  - GPS Status filter dropdown in vehicles list
    - Filter by online/offline/unknown
    - Combined with search and pagination
  - Status badges in vehicles table:
    - 🟢 Online (green) - GPS working, recent data
    - 🔴 Offline (red) - GPS not responding, old data
    - ⚪ Unknown (gray) - No GPS data available
  - "Status Checked" column showing last verification time
  - "Last Position" column enhanced with server data time

- **Repository Enhancements**
  - `VehicleRepository::findWithPaginationAndSearch()` - Added `$gpsStatusFilter` parameter
  - `VehicleRepository::getGpsStatusStatistics()` - Get counts by status

### Changed
- `ParseVehiclesMessageHandler` now automatically updates GPS status when importing vehicles
- Vehicles index page layout reorganized with status dashboard
- Search form combined with status filter and page size selector
- Pagination links preserve GPS status filter

### Technical Details
**GPS Status Logic:**
- Status determined by comparing `lastPositionTime` with current time
- Threshold: 2 hours (configurable via `updateGpsStatus($hours)` parameter)
- Automatic updates on vehicle import and manual status refresh
- Batch processing prevents memory issues with large vehicle counts

**Automation Setup:**
```bash
# Add to crontab for automatic updates every 2 hours
0 */2 * * * cd /path/to/project && php bin/console app:update:vehicle-status --async
```

## [1.0.4] - 2025-10-29

### Added
- **Pagination & Search for Vehicles Page**
  - Implemented pagination with customizable page size (10, 25, 50, 100 items per page)
  - Added search functionality across name, plateNumber, and externalId fields
  - Bootstrap 5 pagination UI with "Previous/Next" buttons and page numbers
  - Page size selector dropdown with auto-submit
  - Results counter showing "Показаны записи X-Y из Z"
  - Search results counter showing "Найдено: X записей"
  - "Reset" button to clear search and show all results
  - All state preserved in URL parameters (bookmarkable pages)
  - Graceful handling of empty search results

### Changed
- `VehicleRepository::findWithPaginationAndSearch()` - New method for paginated queries
  - Case-insensitive search using LOWER() SQL function
  - OR conditions for searching across multiple fields
  - Returns both results array and total count
- `VehicleWebController::index()` - Updated to handle query parameters
  - Accepts `q` (search query), `page` (current page), `limit` (items per page)
  - Validates limit to allowed values [10, 25, 50, 100]
  - Passes pagination state to template
- `templates/vehicle/index.html.twig` - Complete UI overhaul
  - Search form with input field and buttons
  - Page size selector
  - Bootstrap pagination component
  - Improved empty state messages

## [1.0.3] - 2025-10-29

### Added
- **Batch Processing for Vehicle Import**
  - Process 12,138 vehicles in batches of 100 to avoid memory exhaustion
  - `ParseVehiclesMessageHandler::processBatch()` - Batch processing with memory management
  - EntityManager::flush() after each batch
  - EntityManager::clear() to free memory between batches
  - Detailed progress logging for each batch
  - Successfully processes large datasets without exceeding PHP memory limits

### Fixed
- **Critical:** Fixed vehicles parsing - API returns array directly, not wrapped object
  - `getVehicles()` was looking for `$response['Vehicles']` but API returns vehicles array directly
  - Was returning 0 vehicles when API actually returned 12138+ vehicles
  - Now correctly handles both response formats (direct array or wrapped in 'Vehicles' key)
- **Critical:** Fixed empty request body issue in GlonassApiClient
  - Empty array `[]` was being sent as `[]` instead of empty object `{}`
  - API requires `{}` for empty request bodies, not `[]`
  - Now converts empty array to `stdClass()` which JSON-encodes to `{}`
- **Fixed:** X-Auth header was being added even when `requiresAuth=false`
  - Now properly checks `$requiresAuth` flag before adding X-Auth header
  - Login endpoint no longer receives unnecessary null X-Auth header
- **Fixed:** Request body logging caused error with stdClass
  - Added proper type checking before logging request body
  - Now logs JSON string representation of objects

### Changed
- POST/PUT/PATCH requests always send JSON body (even if empty as `{}`)
- GET requests only add query params if data is not empty
- Added debug logging for request body to help troubleshoot API issues

## [1.0.2] - 2025-10-27

### Added
- **Unit Tests:** Comprehensive unit test coverage
  - 59 unit tests with 118 assertions
  - Tests for Message classes (ParseVehiclesMessage, ParseVehicleHistoryMessage, ParseVehicleTracksMessage)
  - Tests for Entity classes (Vehicle, VehicleTrack, CommandHistory)
  - Tests for GlonassApiClient service with mocks
  - PHPUnit configuration with test bootstrap
  - Test documentation in README
- **Authentication Check:**
  - `GlonassApiClient::checkAuth()` - New method to check authentication status via GET /api/v3/auth/check
  - `app:auth:check` - New console command to verify authentication status through API
- **Debug Logging:** Added debug logging to verify token transmission
  - `GlonassApiClient::authenticate()` now logs masked auth token when received
  - `GlonassApiClient::makeRequest()` logs request headers including X-Auth token
  - New helper method `maskToken()` for secure token display (shows first/last 4 chars)
  - New public method `getAuthToken()` for debugging purposes
- **Testing Commands:**
  - `app:debug:auth` - New command to verify authentication flow and token transmission
  - Updated `app:test:api` to display token information
- **Logging Infrastructure:**
  - Installed Symfony Monolog Bundle
  - Configured monolog with debug level logging in dev environment
  - Console handler for real-time log output with -vv flag
  - File handler writing to `var/log/dev.log`

### Changed
- Debug logs now show masked tokens (e.g., "ab12...xy89") instead of full tokens for security

## [1.0.1] - 2025-10-27

### Fixed
- **Rate Limiting:** Improved rate limiting mechanism to guarantee at least 1 second delay between API requests
  - Fixed timing calculation to measure from END of previous request (not start)
  - Added proper time tracking in exception handlers
  - Added 10ms buffer to guarantee >= 1 second delay
  - All requests now correctly wait >= 1 second between calls

### Added
- **Testing Commands:**
  - `app:test:api` - Test API connection and authentication
  - `app:test:vehicle [VEHICLE_ID]` - Test getting specific vehicle by ID
  - `app:test:rate-limit` - Verify rate limiting works correctly with detailed timing measurements

### Changed
- **Rate Limiting Implementation:**
  - `GlonassApiClient::enforceRateLimit()` now calculates delay from last response completion
  - `lastRequestTime` is updated AFTER receiving response (not before sending request)
  - Exception handling ensures `lastRequestTime` is always updated even on errors
  - Added debug logging for rate limit waits

### Technical Details

**Before:**
```
Request 1 starts at T=0
Request 1 ends at T=0.900
Request 2 starts at T=0.900  ← Only 0.9 seconds from start of Request 1!
```

**After:**
```
Request 1 starts at T=0
Request 1 ends at T=0.900
[Wait 0.100 seconds]
Request 2 starts at T=1.000  ← Exactly 1+ second from end of Request 1!
```

This ensures compliance with Glonass API rate limit requirements.

## [1.0.0] - 2025-10-27

### Added
- Initial release
- Symfony 7.3 application structure
- SQLite database with Doctrine ORM
- Entity classes: Vehicle, VehicleTrack, CommandHistory
- Glonass API client with authentication
- Console commands for parsing data
- Symfony Messenger for async processing
- REST API endpoints for CRUD operations
- Web interface with Bootstrap 5
- Comprehensive documentation

### Features
- Parse vehicles from Glonass API
- Store GPS coordinates, speed, course
- Track command history
- Store movement tracks
- Async processing support
- Rate limiting (1 second between requests)
- Web UI for data management
- REST API for programmatic access

### Known Limitations
- API endpoint `/api/v3/vehicles/find` may return 403/429 depending on account permissions
- SQLite not recommended for high-concurrency production use
- Requires valid Glonass API credentials

---

## Legend

- **Added** - New features
- **Changed** - Changes in existing functionality
- **Deprecated** - Soon-to-be removed features
- **Removed** - Removed features
- **Fixed** - Bug fixes
- **Security** - Security fixes

## Versioning

This project follows [Semantic Versioning](https://semver.org/):
- MAJOR version for incompatible API changes
- MINOR version for new functionality in a backwards compatible manner
- PATCH version for backwards compatible bug fixes
