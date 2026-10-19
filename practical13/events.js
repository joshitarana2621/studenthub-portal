/**
 * StudentHub - Practical 13: Event Management with Fetch API & AJAX (No Page Reload)
 *
 * Implements complete asynchronous CRUD operations against /api/events.php:
 * - loadEvents(): Fetches and renders all events asynchronously
 * - addEvent(): Sends POST request to create an event without reload
 * - editEvent(id): Fetches single event details and switches form into edit mode
 * - updateEvent(id): Sends PUT request to update event in-place
 * - deleteEvent(id): Sends DELETE request after confirmation and removes row from DOM
 * - Optional Extension A: Debounced search for live filtering without spamming requests
 * - Optional Extension B: Centralized apiFetch() helper with unified error handling
 */

// Global state tracking
let isEditMode = false;
let currentEditId = null;
let cachedEvents = [];

// DOM Element References (cached once DOM is ready)
let eventForm = null;
let formTitle = null;
let eventIdInput = null;
let titleInput = null;
let categoryInput = null;
let locationInput = null;
let eventDateInput = null;
let endsAtInput = null;
let capacityInput = null;
let statusInput = null;
let descriptionInput = null;
let submitBtn = null;
let cancelBtn = null;
let eventsTableBody = null;
let emptyState = null;
let loadingIndicator = null;
let alertContainer = null;
let searchInput = null;
let categoryFilter = null;
let statusFilter = null;
let clearFilterBtn = null;
let statTotal = null;
let statOpen = null;
let statClosed = null;

/**
 * =========================================================================
 * EXTENSION B: Centralized API Fetch Helper
 * =========================================================================
 * Reusable wrapper around native fetch() that:
 * 1. Automatically includes cookies (credentials: 'same-origin') for PHP sessions
 * 2. Sets Content-Type: application/json for body payloads
 * 3. Inspects response.ok and parses JSON safely
 * 4. Throws structured errors with server-provided messages
 */
async function apiFetch(url, options = {}) {
    const defaultHeaders = {
        'Accept': 'application/json',
    };

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
        defaultHeaders['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }

    const mergedOptions = {
        credentials: 'same-origin',
        ...options,
        headers: {
            ...defaultHeaders,
            ...(options.headers || {})
        }
    };

    try {
        const response = await fetch(url, mergedOptions);
        let result = null;

        // Try parsing JSON response
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            try {
                result = await response.json();
            } catch (jsonErr) {
                throw new Error('Server returned an invalid JSON response.');
            }
        } else {
            const rawText = await response.text();
            throw new Error(`Unexpected server response (HTTP ${response.status}): ${rawText.substring(0, 100)}`);
        }

        // If HTTP status is not 2xx, throw the server's error message
        if (!response.ok) {
            const errorMsg = (result && result.message) ? result.message : `Request failed with HTTP status ${response.status}`;
            const error = new Error(errorMsg);
            error.status = response.status;
            error.data = result;
            throw error;
        }

        return result;
    } catch (networkError) {
        // Distinguish network/connection failures from HTTP status errors
        if (!networkError.status) {
            console.error('Network or CORS error:', networkError);
            throw new Error('Unable to reach server. Please check your network connection and ensure MySQL is running.');
        }
        throw networkError;
    }
}

/**
 * =========================================================================
 * User Feedback & Notification Banners
 * =========================================================================
 */
function showMessage(type, message) {
    if (!alertContainer) return;

    // Clear previous banners
    alertContainer.innerHTML = '';

    const alertBox = document.createElement('div');
    alertBox.className = type === 'success' ? 'alert-banner alert-success' : 'alert-banner alert-error';
    alertBox.setAttribute('role', 'alert');

    const iconSpan = document.createElement('span');
    iconSpan.className = 'alert-icon';
    iconSpan.textContent = type === 'success' ? '✓ ' : '✕ ';

    const textSpan = document.createElement('span');
    textSpan.className = 'alert-text';
    textSpan.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'alert-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label', 'Close notification');
    closeBtn.addEventListener('click', () => {
        alertBox.remove();
    });

    alertBox.appendChild(iconSpan);
    alertBox.appendChild(textSpan);
    alertBox.appendChild(closeBtn);
    alertContainer.appendChild(alertBox);

    // Scroll smoothly to notification so user clearly sees the feedback
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    // Auto-dismiss success banners after 6 seconds
    if (type === 'success') {
        setTimeout(() => {
            if (alertBox.parentElement) {
                alertBox.style.opacity = '0';
                setTimeout(() => alertBox.remove(), 400);
            }
        }, 6000);
    }
}

function showLoading(isLoading) {
    if (!loadingIndicator) return;
    loadingIndicator.style.display = isLoading ? 'flex' : 'none';
}

function updateMetricCards(meta) {
    if (!meta) return;
    if (statTotal) statTotal.textContent = meta.total ?? 0;
    if (statOpen) statOpen.textContent = meta.open ?? 0;
    if (statClosed) statClosed.textContent = meta.closed ?? 0;
}

/**
 * =========================================================================
 * Safe HTML / DOM Helper
 * Prevents XSS by avoiding raw innerHTML for user inputs
 * =========================================================================
 */
function createSafeElement(tag, text, className = '') {
    const el = document.createElement(tag);
    if (text !== null && text !== undefined) {
        el.textContent = text;
    }
    if (className) {
        el.className = className;
    }
    return el;
}

/**
 * Format datetime string into readable Indian / Standard format
 * e.g. "2026-11-12 09:00:00" -> "12 Nov 2026, 09:00 AM"
 */
function formatDisplayDate(dateStr) {
    if (!dateStr) return 'N/A';
    const parsed = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(parsed.getTime())) return dateStr;
    return parsed.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Format date for <input type="datetime-local">
 * e.g. "2026-11-12 09:00:00" -> "2026-11-12T09:00"
 */
function formatInputDateTime(dateStr) {
    if (!dateStr) return '';
    const clean = dateStr.replace(' ', 'T');
    return clean.length > 16 ? clean.substring(0, 16) : clean;
}

/**
 * =========================================================================
 * 1. Read Operations: loadEvents()
 * =========================================================================
 * Fetches events from /api/events.php and renders rows without page reload.
 */
async function loadEvents(searchQuery = '', catFilter = '', statFilter = '') {
    showLoading(true);

    try {
        const params = new URLSearchParams();
        if (searchQuery) params.append('search', searchQuery);
        if (catFilter) params.append('category', catFilter);
        if (statFilter) params.append('status', statFilter);

        const url = `../api/events.php${params.toString() ? '?' + params.toString() : ''}`;
        const response = await apiFetch(url, { method: 'GET' });

        cachedEvents = response.data || [];
        updateMetricCards(response.meta);
        renderEventsTable(cachedEvents);
    } catch (err) {
        console.error('Error loading events:', err);
        showMessage('error', err.message || 'Failed to load events.');
        if (eventsTableBody) {
            eventsTableBody.innerHTML = '';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 7;
            td.className = 'error-row';
            td.textContent = `Error loading events: ${err.message}`;
            tr.appendChild(td);
            eventsTableBody.appendChild(tr);
        }
    } finally {
        showLoading(false);
    }
}

/**
 * Render array of events safely into table rows
 */
function renderEventsTable(events) {
    if (!eventsTableBody) return;
    eventsTableBody.innerHTML = '';

    if (!events || events.length === 0) {
        if (emptyState) emptyState.style.display = 'block';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    events.forEach(event => {
        const tr = document.createElement('tr');
        tr.id = `event-row-${event.id}`;
        tr.className = 'event-row';

        // 1. Event ID
        const tdId = createSafeElement('td', `#${event.id}`, 'col-id');

        // 2. Title & Description
        const tdTitle = document.createElement('td');
        tdTitle.className = 'col-title';
        const titleStrong = createSafeElement('strong', event.title, 'event-title-text');
        const descP = createSafeElement('p', event.description, 'event-desc-text');
        tdTitle.appendChild(titleStrong);
        tdTitle.appendChild(descP);

        // 3. Category Badge
        const tdCat = document.createElement('td');
        tdCat.className = 'col-category';
        const catBadge = createSafeElement('span', event.category || 'Workshop', 'category-badge');
        tdCat.appendChild(catBadge);

        // 4. Venue / Location
        const tdVenue = createSafeElement('td', event.location || event.venue || 'Campus Hall', 'col-venue');

        // 5. Date & Time
        const tdDate = document.createElement('td');
        tdDate.className = 'col-date';
        const startSpan = createSafeElement('span', formatDisplayDate(event.starts_at || event.event_date), 'event-datetime');
        tdDate.appendChild(startSpan);
        if (event.ends_at) {
            const endSmall = createSafeElement('small', `Ends: ${formatDisplayDate(event.ends_at)}`, 'event-end-time');
            tdDate.appendChild(document.createElement('br'));
            tdDate.appendChild(endSmall);
        }

        // 6. Status Badge
        const tdStatus = document.createElement('td');
        tdStatus.className = 'col-status';
        const statusClass = (event.status === 'Open') ? 'status-badge status-open' : 'status-badge status-closed';
        const statusBadge = createSafeElement('span', event.status, statusClass);
        tdStatus.appendChild(statusBadge);

        // 7. Actions (Edit & Delete buttons)
        const tdActions = document.createElement('td');
        tdActions.className = 'col-actions';

        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'btn-action btn-edit';
        editBtn.textContent = 'Edit';
        editBtn.setAttribute('title', `Edit event #${event.id}`);
        editBtn.addEventListener('click', () => editEvent(event.id));

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn-action btn-delete';
        deleteBtn.textContent = 'Delete';
        deleteBtn.setAttribute('title', `Delete event #${event.id}`);
        deleteBtn.addEventListener('click', () => deleteEvent(event.id));

        tdActions.appendChild(editBtn);
        tdActions.appendChild(deleteBtn);

        // Append cells to row
        tr.appendChild(tdId);
        tr.appendChild(tdTitle);
        tr.appendChild(tdCat);
        tr.appendChild(tdVenue);
        tr.appendChild(tdDate);
        tr.appendChild(tdStatus);
        tr.appendChild(tdActions);

        eventsTableBody.appendChild(tr);
    });
}

/**
 * =========================================================================
 * 2. Create Operation: addEvent()
 * =========================================================================
 * Submits form data as JSON via POST to /api/events.php without page reload.
 */
async function addEvent(eventData) {
    showLoading(true);
    setSubmitButtonBusy(true, 'Adding Event...');

    try {
        const response = await apiFetch('../api/events.php', {
            method: 'POST',
            body: eventData
        });

        showMessage('success', response.message || 'Event created successfully.');
        resetForm();

        // Refresh list without page reload
        await loadEvents(
            searchInput ? searchInput.value.trim() : '',
            categoryFilter ? categoryFilter.value : '',
            statusFilter ? statusFilter.value : ''
        );
    } catch (err) {
        console.error('Add event failed:', err);
        showMessage('error', err.message || 'Failed to create event.');
    } finally {
        showLoading(false);
        setSubmitButtonBusy(false, 'Add Event');
    }
}

/**
 * =========================================================================
 * 3. Edit & Update Operations: editEvent() and updateEvent()
 * =========================================================================
 */

/**
 * Fetches event details and switches form into Edit Mode without page reload.
 */
async function editEvent(id) {
    showLoading(true);

    try {
        const response = await apiFetch(`../api/events.php?id=${id}`, { method: 'GET' });
        const event = response.data;

        if (!event) {
            throw new Error(`Event data not found for ID ${id}`);
        }

        // Switch to Edit Mode
        isEditMode = true;
        currentEditId = id;

        if (formTitle) formTitle.textContent = `✏️ Edit Event: ${event.title}`;
        if (eventIdInput) eventIdInput.value = event.id;
        if (titleInput) titleInput.value = event.title;
        if (categoryInput) categoryInput.value = event.category || 'Workshop';
        if (locationInput) locationInput.value = event.location || event.venue || '';
        if (eventDateInput) eventDateInput.value = formatInputDateTime(event.starts_at || event.event_date);
        if (endsAtInput) endsAtInput.value = formatInputDateTime(event.ends_at);
        if (capacityInput) capacityInput.value = event.capacity || '';
        if (statusInput) statusInput.value = event.status || 'Open';
        if (descriptionInput) descriptionInput.value = event.description || '';

        if (submitBtn) {
            submitBtn.textContent = 'Update Event';
            submitBtn.classList.add('btn-update-mode');
        }
        if (cancelBtn) cancelBtn.style.display = 'inline-block';

        // Highlight edited row visually
        document.querySelectorAll('.event-row').forEach(r => r.classList.remove('row-editing'));
        const row = document.getElementById(`event-row-${id}`);
        if (row) row.classList.add('row-editing');

        // Scroll smoothly to form
        if (eventForm) {
            eventForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } catch (err) {
        console.error('Edit event lookup failed:', err);
        showMessage('error', err.message || 'Failed to fetch event details for editing.');
    } finally {
        showLoading(false);
    }
}

/**
 * Submits updated data via PUT to /api/events.php without page reload.
 */
async function updateEvent(id, eventData) {
    showLoading(true);
    setSubmitButtonBusy(true, 'Updating Event...');

    try {
        const response = await apiFetch(`../api/events.php?id=${id}`, {
            method: 'PUT',
            body: eventData
        });

        showMessage('success', response.message || 'Event updated successfully.');
        resetForm();

        // Refresh list without page reload
        await loadEvents(
            searchInput ? searchInput.value.trim() : '',
            categoryFilter ? categoryFilter.value : '',
            statusFilter ? statusFilter.value : ''
        );
    } catch (err) {
        console.error('Update event failed:', err);
        showMessage('error', err.message || 'Failed to update event.');
    } finally {
        showLoading(false);
        setSubmitButtonBusy(false, 'Update Event');
    }
}

/**
 * =========================================================================
 * 4. Delete Operation: deleteEvent()
 * =========================================================================
 * Confirms deletion, sends DELETE request to /api/events.php, and removes row from DOM.
 */
async function deleteEvent(id) {
    const event = cachedEvents.find(e => e.id === id);
    const eventName = event ? `"${event.title}"` : `ID #${id}`;

    const confirmed = window.confirm(
        `Are you sure you want to permanently delete event ${eventName}?\n\nThis action cannot be undone.`
    );
    if (!confirmed) return;

    showLoading(true);

    try {
        const response = await apiFetch(`../api/events.php?id=${id}`, {
            method: 'DELETE'
        });

        showMessage('success', response.message || 'Event deleted successfully.');

        // If currently editing this event, reset the form
        if (isEditMode && currentEditId === id) {
            resetForm();
        }

        // Animate row removal smoothly in the DOM without full page reload
        const row = document.getElementById(`event-row-${id}`);
        if (row) {
            row.style.transition = 'all 0.3s ease';
            row.style.backgroundColor = '#fee2e2';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                // Check if table is now empty
                if (eventsTableBody && eventsTableBody.children.length === 0) {
                    if (emptyState) emptyState.style.display = 'block';
                }
            }, 300);
        }

        // Also refresh cached events and counts
        loadEvents(
            searchInput ? searchInput.value.trim() : '',
            categoryFilter ? categoryFilter.value : '',
            statusFilter ? statusFilter.value : ''
        );
    } catch (err) {
        console.error('Delete event failed:', err);
        showMessage('error', err.message || 'Failed to delete event.');
    } finally {
        showLoading(false);
    }
}

/**
 * =========================================================================
 * Form State & Reset Management
 * =========================================================================
 */
function resetForm() {
    isEditMode = false;
    currentEditId = null;

    if (eventForm) eventForm.reset();
    if (eventIdInput) eventIdInput.value = '';
    if (formTitle) formTitle.textContent = 'Schedule New Campus Event';
    if (submitBtn) {
        submitBtn.textContent = 'Add Event';
        submitBtn.classList.remove('btn-update-mode');
        submitBtn.disabled = false;
    }
    if (cancelBtn) cancelBtn.style.display = 'none';

    document.querySelectorAll('.event-row').forEach(r => r.classList.remove('row-editing'));
}

function setSubmitButtonBusy(isBusy, text) {
    if (!submitBtn) return;
    submitBtn.disabled = isBusy;
    submitBtn.textContent = text;
}

/**
 * Client-side validation before sending AJAX request
 */
function validateEventForm() {
    const title = titleInput ? titleInput.value.trim() : '';
    const description = descriptionInput ? descriptionInput.value.trim() : '';
    const eventDate = eventDateInput ? eventDateInput.value.trim() : '';
    const endsAt = endsAtInput ? endsAtInput.value.trim() : '';

    if (!title) {
        showMessage('error', 'Please enter an event title.');
        if (titleInput) titleInput.focus();
        return false;
    }

    if (title.length > 150) {
        showMessage('error', 'Event title cannot exceed 150 characters.');
        if (titleInput) titleInput.focus();
        return false;
    }

    if (!description) {
        showMessage('error', 'Please enter an event description.');
        if (descriptionInput) descriptionInput.focus();
        return false;
    }

    if (!eventDate) {
        showMessage('error', 'Please specify the event date and time.');
        if (eventDateInput) eventDateInput.focus();
        return false;
    }

    if (endsAt) {
        const startMs = new Date(eventDate).getTime();
        const endMs = new Date(endsAt).getTime();
        if (endMs < startMs) {
            showMessage('error', 'Event end time cannot be earlier than start date and time.');
            if (endsAtInput) endsAtInput.focus();
            return false;
        }
    }

    return true;
}

/**
 * Handle form submission for both Add and Update
 */
function handleFormSubmit(event) {
    event.preventDefault();

    if (!validateEventForm()) {
        return;
    }

    const payload = {
        title: titleInput.value.trim(),
        description: descriptionInput.value.trim(),
        category: categoryInput ? categoryInput.value : 'Workshop',
        location: locationInput ? locationInput.value.trim() : 'Campus Main Hall',
        venue: locationInput ? locationInput.value.trim() : 'Campus Main Hall',
        event_date: eventDateInput.value,
        starts_at: eventDateInput.value,
        ends_at: endsAtInput && endsAtInput.value ? endsAtInput.value : null,
        capacity: capacityInput && capacityInput.value ? parseInt(capacityInput.value, 10) : null,
        status: statusInput ? statusInput.value : 'Open'
    };

    if (isEditMode && currentEditId) {
        payload.id = currentEditId;
        updateEvent(currentEditId, payload);
    } else {
        addEvent(payload);
    }
}

/**
 * =========================================================================
 * EXTENSION A: Debounce Utility for Live Search
 * =========================================================================
 * Debounce delays executing a function until after `delay` milliseconds have
 * elapsed since the last time it was invoked. This prevents spamming the
 * server with an API request on every keystroke as the user types.
 */
function debounce(fn, delay = 350) {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn.apply(this, args), delay);
    };
}

/**
 * =========================================================================
 * Initialization & Event Listeners
 * =========================================================================
 */
document.addEventListener('DOMContentLoaded', () => {
    // Cache DOM nodes
    eventForm = document.getElementById('eventForm');
    formTitle = document.getElementById('formTitle');
    eventIdInput = document.getElementById('eventId');
    titleInput = document.getElementById('eventTitle');
    categoryInput = document.getElementById('eventCategory');
    locationInput = document.getElementById('eventLocation');
    eventDateInput = document.getElementById('eventDate');
    endsAtInput = document.getElementById('eventEndsAt');
    capacityInput = document.getElementById('eventCapacity');
    statusInput = document.getElementById('eventStatus');
    descriptionInput = document.getElementById('eventDescription');
    submitBtn = document.getElementById('submitBtn');
    cancelBtn = document.getElementById('cancelBtn');

    eventsTableBody = document.getElementById('eventsTableBody');
    emptyState = document.getElementById('emptyState');
    loadingIndicator = document.getElementById('loadingIndicator');
    alertContainer = document.getElementById('alertContainer');

    searchInput = document.getElementById('eventSearchInput');
    categoryFilter = document.getElementById('categoryFilter');
    statusFilter = document.getElementById('statusFilter');
    clearFilterBtn = document.getElementById('clearFiltersBtn');

    statTotal = document.getElementById('statTotal');
    statOpen = document.getElementById('statOpen');
    statClosed = document.getElementById('statClosed');

    // Attach form submit listener
    if (eventForm) {
        eventForm.addEventListener('submit', handleFormSubmit);
    }

    // Attach Cancel Edit listener
    if (cancelBtn) {
        cancelBtn.addEventListener('click', resetForm);
    }

    // Attach Debounced Search Input
    if (searchInput) {
        const handleSearchDebounced = debounce(() => {
            const query = searchInput.value.trim();
            const cat = categoryFilter ? categoryFilter.value : '';
            const stat = statusFilter ? statusFilter.value : '';
            loadEvents(query, cat, stat);
        }, 350);

        searchInput.addEventListener('input', handleSearchDebounced);
    }

    // Filter Dropdown Change Listeners
    if (categoryFilter) {
        categoryFilter.addEventListener('change', () => {
            const query = searchInput ? searchInput.value.trim() : '';
            const cat = categoryFilter.value;
            const stat = statusFilter ? statusFilter.value : '';
            loadEvents(query, cat, stat);
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            const query = searchInput ? searchInput.value.trim() : '';
            const cat = categoryFilter ? categoryFilter.value : '';
            const stat = statusFilter.value;
            loadEvents(query, cat, stat);
        });
    }

    // Clear Filters Button
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (categoryFilter) categoryFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            loadEvents();
        });
    }

    // Initial Load of Events on Page Open
    loadEvents();
});
