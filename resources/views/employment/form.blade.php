@extends('layouts.app')

@push('head')
<link href="https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css" rel="stylesheet">
<style>
    /* Match Bootstrap form-control styling for Choices.js */
    .choices__inner {
        border-radius: 0.375rem !important;
        min-height: calc(1.5em + 0.75rem + 2px);
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        border: 1px solid #dee2e6;
    }

    .choices__inner:focus-within,
    .choices.is-focused .choices__inner {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .choices__list--dropdown {
        border-radius: 0.375rem;
    }
</style>
@endpush

@section('content')
    <!-- Notification Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="modalHeader">
                    <h5 class="modal-title" id="modalTitle">Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Message will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if($affiliations->count())
        <div class="container mb-4">
            <h3 class="mb-3">Your Employment History</h3>

            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($affiliations as $a)
                        <tr>
                            <td class="fw-bold">{{ $a->company->name }}</td>
                            <td>{{ \Carbon\Carbon::parse($a->start_date)->format('M Y') }}</td>
                            <td>{{ $a->end_date ? \Carbon\Carbon::parse($a->end_date)->format('M Y') : 'Present' }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('employment.edit', $a->id) }}" class="btn btn-sm btn-primary">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('employment.destroy', $a->id) }}" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this employment record?')">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="d-md-none">
                @foreach($affiliations as $a)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">{{ $a->company->name }}</h5>
                            <p class="card-text mb-2">
                                <strong>Start:</strong> {{ \Carbon\Carbon::parse($a->start_date)->format('M Y') }}<br>
                                <strong>End:</strong> {{ $a->end_date ? \Carbon\Carbon::parse($a->end_date)->format('M Y') : 'Present' }}
                            </p>
                            <div class="d-flex gap-2">
                                <a href="{{ route('employment.edit', $a->id) }}" class="btn btn-sm btn-primary">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('employment.destroy', $a->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this employment record?')">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    <div class="container mb-4">
        <h3>Add Employment History</h3>

        @if(session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif
        @if($errors->has('conflict'))
            <div class="alert alert-danger" role="alert">
                {{ $errors->first('conflict') }}
            </div>
        @endif

        <form method="POST" action="{{ url('/employment') }}">
            @csrf

            <div class="row mb-3">
                <label for="company_id" class="col-sm-2 col-form-label">Select Company</label>
                <div class="col-sm-10">
                    <select name="company_id" id="company_id" class="form-control">
                        <option value="" disabled selected>Select an option</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Select the company you work (or worked) for.
                        <a href="#" id="propose-company-link">Can't find your company? Propose it here.</a>
                    </div>
                </div>
                @error('company_id')
                <div class="alert alert-danger" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <div id="propose-company-section" class="collapse mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6>Propose a New Company</h6>
                        <p class="small text-muted">
                            We'll review your submission. Fill in as much information as you have - any missing details will be added by our team during approval.
                        </p>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Company Name *</label>
                                <input type="text" id="proposed_company_name" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Website</label>
                                <input type="url" id="proposed_company_website" class="form-control"
                                       placeholder="https://example.com">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" id="proposed_company_email" class="form-control"
                                       placeholder="info@example.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Phone</label>
                                <input type="tel" id="proposed_company_phone" class="form-control"
                                       placeholder="+1-234-567-8900">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>LinkedIn Company URL (recommended)</label>
                            <input type="url" id="proposed_company_linkedin" class="form-control"
                                   placeholder="https://www.linkedin.com/company/...">
                            <div class="form-text">
                                Providing a LinkedIn URL helps us approve your company faster.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Address</label>
                            <input type="text" id="proposed_company_address" class="form-control"
                                   placeholder="123 Main Street">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>City</label>
                                <input type="text" id="proposed_company_city" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>State/Province</label>
                                <input type="text" id="proposed_company_state" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Zip/Postal Code</label>
                                <input type="text" id="proposed_company_zip" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label>Country</label>
                            <select id="proposed_company_country_code" class="form-control">
                                <option value="">Select a country</option>
                                @foreach(collect(countries())->sortBy('name') as $country)
                                    <option value="{{ $country['iso_3166_1_alpha3'] }}">{{ $country['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="button" id="submit-proposed-company" class="btn btn-sm btn-success">
                            Submit for Approval
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cancel-propose-company">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label for="start_date" class="col-sm-2 col-form-label">Start Date</label>
                <div class="col-sm-10">
                    <input type="date" name="start_date" id="start_date" class="form-control" required>
                    @error('start_date')
                    <div class="alert alert-danger" role="alert">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Select the date when you started working for the company above.
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label for="end_date" class="col-sm-2 col-form-label">End Date (optional)</label>
                <div class="col-sm-10">
                    <input type="date" name="end_date" id="end_date" class="form-control">
                    @error('end_date')
                    <div class="alert alert-danger" role="alert">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Select the date when you stopped working for the company above. If you're currently still employed at the company, just leave this empty.
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success">
                Save Employment History
            </button>
        </form>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js"></script>
<script>
// Helper function to show modal notifications
function showModal(title, message, type = 'info') {
    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
    const modalHeader = document.getElementById('modalHeader');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');

    // Set title and message
    modalTitle.textContent = title;
    modalBody.textContent = message;

    // Set header color based on type
    modalHeader.className = 'modal-header';
    if (type === 'success') {
        modalHeader.classList.add('bg-success', 'text-white');
    } else if (type === 'error') {
        modalHeader.classList.add('bg-danger', 'text-white');
    } else {
        modalHeader.classList.add('bg-primary', 'text-white');
    }

    modal.show();
}

// Initialize Choices.js (same library as Filament admin)
const companySelect = new Choices('#company_id', {
    allowHTML: false,
    duplicateItemsAllowed: false,
    itemSelectText: '',
    searchEnabled: true,
    searchPlaceholderValue: 'Start typing to search',
    shouldSort: false,
    removeItemButton: false,
    noResultsText: 'No results found',
    noChoicesText: 'Start typing to search',
});

// Show propose company form
document.getElementById('propose-company-link').addEventListener('click', function(e) {
    e.preventDefault();
    new bootstrap.Collapse(document.getElementById('propose-company-section')).show();
});

// Helper to clear propose company form
function clearProposeForm() {
    document.getElementById('proposed_company_name').value = '';
    document.getElementById('proposed_company_website').value = '';
    document.getElementById('proposed_company_email').value = '';
    document.getElementById('proposed_company_phone').value = '';
    document.getElementById('proposed_company_linkedin').value = '';
    document.getElementById('proposed_company_address').value = '';
    document.getElementById('proposed_company_city').value = '';
    document.getElementById('proposed_company_state').value = '';
    document.getElementById('proposed_company_zip').value = '';
    document.getElementById('proposed_company_country_code').value = '';
}

// Hide propose company form
document.getElementById('cancel-propose-company').addEventListener('click', function() {
    new bootstrap.Collapse(document.getElementById('propose-company-section')).hide();
    clearProposeForm();
});

// Submit proposed company via AJAX
document.getElementById('submit-proposed-company').addEventListener('click', function() {
    const name = document.getElementById('proposed_company_name').value.trim();
    const website = document.getElementById('proposed_company_website').value.trim();
    const email = document.getElementById('proposed_company_email').value.trim();
    const phone = document.getElementById('proposed_company_phone').value.trim();
    const linkedin = document.getElementById('proposed_company_linkedin').value.trim();
    const address = document.getElementById('proposed_company_address').value.trim();
    const city = document.getElementById('proposed_company_city').value.trim();
    const state = document.getElementById('proposed_company_state').value.trim();
    const zip = document.getElementById('proposed_company_zip').value.trim();
    const countryCode = document.getElementById('proposed_company_country_code').value.trim();

    if (!name) {
        showModal('Validation Error', 'Please enter a company name', 'error');
        return;
    }

    // Submit via AJAX - send all fields, backend will use placeholders for empty ones
    fetch('/api/companies/propose', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            name: name,
            website: website,
            email: email,
            phone: phone,
            linkedin_url: linkedin,
            address: address,
            city: city,
            state: state,
            zip: zip,
            country_code: countryCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Check if this is a warning (existing pending company)
            if (data.warning) {
                // Add pending company to dropdown and auto-select it
                companySelect.setChoices([
                    { value: data.company.id, label: data.company.name, selected: true }
                ], 'value', 'label', false);

                // Hide propose form
                new bootstrap.Collapse(document.getElementById('propose-company-section')).hide();

                // Clear form
                clearProposeForm();

                // Show informational modal
                showModal(
                    'Company Already Submitted',
                    data.message + ' We\'ve added it to your selection.',
                    'info'
                );
            } else {
                // Normal success - newly created company
                // Add new pending company to dropdown using Choices.js API
                companySelect.setChoices([
                    { value: data.company.id, label: data.company.name, selected: true }
                ], 'value', 'label', false);

                // Hide propose form
                new bootstrap.Collapse(document.getElementById('propose-company-section')).hide();

                // Clear form
                clearProposeForm();

                // Show success message
                showModal('Success', 'Company proposed! You can now use it. We\'ll review it shortly.', 'success');
            }
        } else {
            // Error cases (approved or rejected companies, validation errors)
            showModal('Error', data.message || 'Failed to propose company', 'error');
        }
    })
    .catch(error => {
        showModal('Error', 'Error proposing company. Please try again.', 'error');
        console.error(error);
    });
});
</script>
@endpush
