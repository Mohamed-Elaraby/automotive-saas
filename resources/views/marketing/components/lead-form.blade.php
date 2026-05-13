@php
    /** @var string $action */
    /** @var string $kind */
    /** @var string $locale */
    /** @var array $businessTypes */
    /** @var array $interestedSystems */
    /** @var array $preferredLanguages */
    /** @var array $countries */
    /** @var string|null $defaultInterestedSystem */
    $defaultInterestedSystem = $defaultInterestedSystem ?? null;
@endphp
@if($errors->any())
    <div class="mkt-error-box" role="alert" aria-live="assertive">
        <strong>{{ __('marketing.form.errors_heading') }}</strong>
        <ul style="margin:.5rem 0 0;padding-inline-start:1.25rem;">
            @foreach($errors->all() as $msg)
                <li>{{ $msg }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" novalidate>
    @csrf

    <div class="mkt-form-honeypot" aria-hidden="true">
        <label>{{ __('marketing.form.honeypot_label') }} <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="mkt-form-row">
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="full_name">{{ __('marketing.form.full_name') }} <span aria-hidden="true">*</span></label>
            <input id="full_name" name="full_name" type="text" required maxlength="120" class="mkt-form-control" value="{{ old('full_name') }}">
            @error('full_name') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="company_name">{{ __('marketing.form.company_name') }}</label>
            <input id="company_name" name="company_name" type="text" maxlength="160" class="mkt-form-control" value="{{ old('company_name') }}">
            @error('company_name') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="mkt-form-row">
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="email">{{ __('marketing.form.email') }} <span aria-hidden="true">*</span></label>
            <input id="email" name="email" type="email" required maxlength="160" class="mkt-form-control" value="{{ old('email') }}">
            @error('email') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="phone">{{ __('marketing.form.phone') }}</label>
            <input id="phone" name="phone" type="tel" maxlength="64" class="mkt-form-control" value="{{ old('phone') }}" placeholder="+971 ...">
            @error('phone') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="mkt-form-row">
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="business_type">{{ __('marketing.form.business_type') }}</label>
            <select id="business_type" name="business_type" class="mkt-form-control">
                <option value="">{{ __('marketing.form.select_placeholder') }}</option>
                @foreach($businessTypes as $bt)
                    <option value="{{ $bt }}" @selected(old('business_type') === $bt)>{{ __("marketing.business_types.{$bt}") }}</option>
                @endforeach
            </select>
            @error('business_type') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="country">{{ __('marketing.form.country') }}</label>
            <select id="country" name="country" class="mkt-form-control">
                <option value="">{{ __('marketing.form.select_placeholder') }}</option>
                @foreach($countries as $c)
                    <option value="{{ $c }}" @selected(old('country') === $c)>{{ __("marketing.countries.{$c}") }}</option>
                @endforeach
            </select>
            @error('country') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="mkt-form-row">
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="interested_system">{{ __('marketing.form.interested_system') }}</label>
            <select id="interested_system" name="interested_system" class="mkt-form-control">
                <option value="">{{ __('marketing.form.select_placeholder') }}</option>
                @foreach($interestedSystems as $sys)
                    <option value="{{ $sys }}" @selected((old('interested_system') ?? $defaultInterestedSystem) === $sys)>{{ __("marketing.interested_systems.{$sys}") }}</option>
                @endforeach
            </select>
            @error('interested_system') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
        <div class="mkt-form-group">
            <label class="mkt-form-label" for="branches_count">{{ __('marketing.form.branches_count') }}</label>
            <input id="branches_count" name="branches_count" type="number" min="1" max="9999" class="mkt-form-control" value="{{ old('branches_count') }}">
            @error('branches_count') <div class="mkt-form-error">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="mkt-form-group">
        <label class="mkt-form-label" for="preferred_language">{{ __('marketing.form.preferred_language') }}</label>
        <select id="preferred_language" name="preferred_language" class="mkt-form-control">
            <option value="">{{ __('marketing.form.select_placeholder') }}</option>
            @foreach($preferredLanguages as $lang)
                <option value="{{ $lang }}" @selected((old('preferred_language') ?? $locale) === $lang)>{{ __("marketing.languages.{$lang}") }}</option>
            @endforeach
        </select>
    </div>

    <div class="mkt-form-group">
        <label class="mkt-form-label" for="message">{{ __('marketing.form.message') }}</label>
        <textarea id="message" name="message" rows="4" maxlength="4000" class="mkt-form-control">{{ old('message') }}</textarea>
        @error('message') <div class="mkt-form-error">{{ $message }}</div> @enderror
    </div>

    <button type="submit" class="mkt-btn mkt-btn-primary mkt-btn-lg" style="width:100%;">
        {{ __("marketing.form.submit_{$kind}") }}
    </button>

    <p class="mkt-form-help" style="margin-top:1rem;text-align:center;">
        {{ __('marketing.form.privacy_note') }}
    </p>
</form>
