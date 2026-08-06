@extends('admin.layout')

@section('title', 'Настройки агента')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Панель управления</a></li>
    <li class="breadcrumb-item active" aria-current="page">Настройки</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline shadow-sm settings-card">
            <form method="post" action="{{ route('admin.settings.update') }}" novalidate>
                @csrf
                @method('PUT')

                <div class="card-header">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                        <div>
                            <h3 class="card-title mb-1">
                                <i class="bi bi-sliders me-2 text-primary" aria-hidden="true"></i>
                                Параметры ИИ-агента
                            </h3>
                            <p class="mb-0 small text-body-secondary">
                                Управление созданием публикаций и правилами объединения событий.
                            </p>
                        </div>
                        @cannot('manage-settings')
                        <span class="badge text-bg-secondary ms-sm-auto">
                            <i class="bi bi-eye me-1" aria-hidden="true"></i>
                            Только просмотр
                        </span>
                        @endcannot
                    </div>
                </div>

                <div class="card-body">
                    <section aria-labelledby="agent-mode-heading">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="settings-section-icon text-bg-primary">
                                <i class="bi bi-power" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h4 class="h6 fw-bold mb-0" id="agent-mode-heading">Режим работы агента</h4>
                                <div class="small text-body-secondary">Настройка автоматического создания публикаций.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="settings-option rounded border bg-body-tertiary p-3 h-100">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="settings-option-icon text-bg-info">
                                            <i class="bi bi-send-check" aria-hidden="true"></i>
                                        </span>
                                        <div class="flex-grow-1">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input" type="checkbox" role="switch" id="automatic-publication" name="automatic_publication" value="1" @checked(old('automatic_publication', $settings['automatic_publication'])) @cannot('manage-settings') disabled @endcannot>
                                                <label class="form-check-label fw-semibold" for="automatic-publication">Автоматическое создание публикаций</label>
                                            </div>
                                            <div class="form-text mt-0">После проверок материал сохраняется в разделе готовых постов.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <hr class="my-4">

                    <section aria-labelledby="ai-provider-heading">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="settings-section-icon text-bg-primary">
                                <i class="bi bi-cpu" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h4 class="h6 fw-bold mb-0" id="ai-provider-heading">Провайдер искусственного интеллекта</h4>
                                <div class="small text-body-secondary">Выберите активный режим, затем настройте подключения на вкладках ниже.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            @foreach ($aiProviderOptions as $code => $option)
                                @php
                                    $providerInputId = 'ai-provider-'.str_replace('_', '-', $code);
                                    $providerSelected = old('ai_provider', $aiSettings['provider']) === $code;
                                @endphp
                                <div class="col-md-6 col-xl-3">
                                    <label class="ai-provider-option rounded border bg-body-tertiary p-3 h-100 d-block {{ $option['available'] ? '' : 'opacity-75' }}" for="{{ $providerInputId }}">
                                        <span class="d-flex align-items-start gap-3">
                                            <span class="settings-option-icon {{ $option['available'] ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                                <i class="bi bi-robot" aria-hidden="true"></i>
                                            </span>
                                            <span class="flex-grow-1">
                                                <span class="form-check mb-1">
                                                    <input
                                                        class="form-check-input ai-provider-checkbox @error('ai_provider') is-invalid @enderror"
                                                        type="checkbox"
                                                        id="{{ $providerInputId }}"
                                                        name="ai_provider"
                                                        value="{{ $code }}"
                                                        @checked($providerSelected)
                                                        @disabled(! $option['available'])
                                                        @cannot('manage-settings') disabled @endcannot
                                                    >
                                                    <span class="form-check-label fw-semibold">{{ $option['label'] }}</span>
                                                </span>
                                                @if ($code === 'rules')
                                                    <span class="badge text-bg-info">Встроенный режим</span>
                                                @elseif ($option['available'])
                                                    <span class="badge text-bg-success">Адаптер подключён</span>
                                                @else
                                                    <span class="badge text-bg-secondary">Адаптер не подключён</span>
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('ai_provider')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror

                        @php
                            $connectionTabs = [
                                'gigachat' => [
                                    'label' => 'GigaChat',
                                    'icon' => 'bi-chat-square-dots',
                                    'fields' => [
                                        ['name' => 'gigachat_auth_url', 'key' => 'auth_url', 'label' => 'URL авторизации', 'type' => 'url', 'column' => 'col-lg-6', 'required' => true],
                                        ['name' => 'gigachat_api_url', 'key' => 'api_url', 'label' => 'URL API', 'type' => 'url', 'column' => 'col-lg-6', 'required' => true],
                                        ['name' => 'gigachat_scope', 'key' => 'scope', 'label' => 'Scope', 'type' => 'text', 'column' => 'col-md-6 col-lg-4', 'required' => true],
                                        ['name' => 'gigachat_model', 'key' => 'model', 'label' => 'Модель', 'type' => 'text', 'column' => 'col-md-6 col-lg-4', 'required' => true],
                                        ['name' => 'gigachat_embedding_model', 'key' => 'embedding_model', 'label' => 'Модель embeddings', 'type' => 'text', 'column' => 'col-md-6 col-lg-4', 'required' => true],
                                        ['name' => 'gigachat_timeout', 'key' => 'timeout', 'label' => 'Таймаут запроса', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 600, 'suffix' => 'сек.'],
                                        ['name' => 'gigachat_connect_timeout', 'key' => 'connect_timeout', 'label' => 'Таймаут подключения', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 120, 'suffix' => 'сек.'],
                                        ['name' => 'gigachat_max_attempts', 'key' => 'max_attempts', 'label' => 'Количество попыток', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 10],
                                    ],
                                    'switches' => [
                                        ['name' => 'gigachat_embedding_fallback', 'key' => 'embedding_fallback', 'label' => 'Резервный расчёт embeddings', 'help' => 'Использовать локальный расчёт, если API embeddings недоступен.'],
                                        ['name' => 'gigachat_verify_ssl', 'key' => 'verify_ssl', 'label' => 'Проверять SSL-сертификат', 'help' => 'Обязательная защита учётных данных; отключение недоступно.', 'locked' => true],
                                    ],
                                    'credentials' => [
                                        ['name' => 'gigachat_auth_key', 'state' => 'auth_key_configured', 'label' => 'Authorization Key'],
                                        ['name' => 'gigachat_client_id', 'state' => 'client_id_configured', 'label' => 'Client ID'],
                                        ['name' => 'gigachat_client_secret', 'state' => 'client_secret_configured', 'label' => 'Client Secret'],
                                    ],
                                    'clear_name' => 'clear_gigachat_secrets',
                                    'clear_label' => 'Удалить сохранённые секреты GigaChat',
                                ],
                                'yandexgpt' => [
                                    'label' => 'YandexGPT',
                                    'icon' => 'bi-stars',
                                    'fields' => [
                                        ['name' => 'yandexgpt_api_url', 'key' => 'api_url', 'label' => 'URL API', 'type' => 'url', 'column' => 'col-lg-6', 'required' => true],
                                        ['name' => 'yandexgpt_folder_id', 'key' => 'folder_id', 'label' => 'Folder ID', 'type' => 'text', 'column' => 'col-lg-6'],
                                        ['name' => 'yandexgpt_model', 'key' => 'model', 'label' => 'Модель', 'type' => 'text', 'column' => 'col-md-6', 'required' => true],
                                        ['name' => 'yandexgpt_embedding_model', 'key' => 'embedding_model', 'label' => 'Модель embeddings', 'type' => 'text', 'column' => 'col-md-6', 'required' => true],
                                        ['name' => 'yandexgpt_timeout', 'key' => 'timeout', 'label' => 'Таймаут запроса', 'type' => 'number', 'column' => 'col-md-6 col-lg-3', 'required' => true, 'min' => 1, 'max' => 600, 'suffix' => 'сек.'],
                                        ['name' => 'yandexgpt_connect_timeout', 'key' => 'connect_timeout', 'label' => 'Таймаут подключения', 'type' => 'number', 'column' => 'col-md-6 col-lg-3', 'required' => true, 'min' => 1, 'max' => 120, 'suffix' => 'сек.'],
                                        ['name' => 'yandexgpt_max_attempts', 'key' => 'max_attempts', 'label' => 'Количество попыток', 'type' => 'number', 'column' => 'col-md-6 col-lg-3', 'required' => true, 'min' => 1, 'max' => 10],
                                    ],
                                    'switches' => [
                                        ['name' => 'yandexgpt_verify_ssl', 'key' => 'verify_ssl', 'label' => 'Проверять SSL-сертификат', 'help' => 'Обязательная защита учётных данных; отключение недоступно.', 'locked' => true],
                                    ],
                                    'credentials' => [
                                        ['name' => 'yandexgpt_api_key', 'state' => 'api_key_configured', 'label' => 'API Key'],
                                        ['name' => 'yandexgpt_iam_token', 'state' => 'iam_token_configured', 'label' => 'IAM-токен'],
                                    ],
                                    'clear_name' => 'clear_yandexgpt_credentials',
                                    'clear_label' => 'Удалить сохранённые учётные данные YandexGPT',
                                ],
                                'openai' => [
                                    'label' => 'OpenAI',
                                    'icon' => 'bi-hexagon',
                                    'fields' => [
                                        ['name' => 'openai_api_url', 'key' => 'api_url', 'label' => 'URL API', 'type' => 'url', 'column' => 'col-lg-6', 'required' => true],
                                        ['name' => 'openai_model', 'key' => 'model', 'label' => 'Модель', 'type' => 'text', 'column' => 'col-md-6 col-lg-3', 'required' => true],
                                        ['name' => 'openai_embedding_model', 'key' => 'embedding_model', 'label' => 'Модель embeddings', 'type' => 'text', 'column' => 'col-md-6 col-lg-3', 'required' => true],
                                        ['name' => 'openai_organization', 'key' => 'organization', 'label' => 'Organization', 'type' => 'text', 'column' => 'col-md-6'],
                                        ['name' => 'openai_project', 'key' => 'project', 'label' => 'Project', 'type' => 'text', 'column' => 'col-md-6'],
                                        ['name' => 'openai_timeout', 'key' => 'timeout', 'label' => 'Таймаут запроса', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 600, 'suffix' => 'сек.'],
                                        ['name' => 'openai_connect_timeout', 'key' => 'connect_timeout', 'label' => 'Таймаут подключения', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 120, 'suffix' => 'сек.'],
                                        ['name' => 'openai_max_attempts', 'key' => 'max_attempts', 'label' => 'Количество попыток', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 10],
                                    ],
                                    'switches' => [
                                        ['name' => 'openai_verify_ssl', 'key' => 'verify_ssl', 'label' => 'Проверять SSL-сертификат', 'help' => 'Обязательная защита учётных данных; отключение недоступно.', 'locked' => true],
                                    ],
                                    'credentials' => [
                                        ['name' => 'openai_api_key', 'state' => 'api_key_configured', 'label' => 'API Key'],
                                    ],
                                    'clear_name' => 'clear_openai_credentials',
                                    'clear_label' => 'Удалить сохранённый API Key OpenAI',
                                ],
                                'gemini' => [
                                    'label' => 'Google Gemini',
                                    'icon' => 'bi-gem',
                                    'fields' => [
                                        ['name' => 'gemini_api_url', 'key' => 'api_url', 'label' => 'URL API', 'type' => 'url', 'column' => 'col-lg-6', 'required' => true],
                                        ['name' => 'gemini_model', 'key' => 'model', 'label' => 'Модель', 'type' => 'text', 'column' => 'col-md-6 col-lg-3', 'required' => true],
                                        ['name' => 'gemini_embedding_model', 'key' => 'embedding_model', 'label' => 'Модель embeddings', 'type' => 'text', 'column' => 'col-md-6 col-lg-3', 'required' => true],
                                        ['name' => 'gemini_timeout', 'key' => 'timeout', 'label' => 'Таймаут запроса', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 600, 'suffix' => 'сек.'],
                                        ['name' => 'gemini_connect_timeout', 'key' => 'connect_timeout', 'label' => 'Таймаут подключения', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 120, 'suffix' => 'сек.'],
                                        ['name' => 'gemini_max_attempts', 'key' => 'max_attempts', 'label' => 'Количество попыток', 'type' => 'number', 'column' => 'col-md-4', 'required' => true, 'min' => 1, 'max' => 10],
                                    ],
                                    'switches' => [
                                        ['name' => 'gemini_verify_ssl', 'key' => 'verify_ssl', 'label' => 'Проверять SSL-сертификат', 'help' => 'Обязательная защита учётных данных; отключение недоступно.', 'locked' => true],
                                    ],
                                    'credentials' => [
                                        ['name' => 'gemini_api_key', 'state' => 'api_key_configured', 'label' => 'Gemini API Key'],
                                    ],
                                    'clear_name' => 'clear_gemini_credentials',
                                    'clear_label' => 'Удалить сохранённый API Key Gemini',
                                    'notice' => 'Gemini API должен быть доступен в регионе, из которого сервер выполняет запросы.',
                                ],
                            ];
                            $settingsTabErrors = [];

                            foreach ($connectionTabs as $tabCode => $tab) {
                                $tabFieldNames = array_column($tab['fields'], 'name');
                                $tabFieldNames = array_merge($tabFieldNames, array_column($tab['switches'], 'name'));
                                $tabFieldNames = array_merge($tabFieldNames, array_column($tab['credentials'], 'name'));
                                $tabFieldNames[] = $tab['clear_name'];
                                $settingsTabErrors[$tabCode] = $errors->hasAny($tabFieldNames);
                            }

                            $initialSettingsTab = old('settings_tab', session('settings_tab', 'gigachat'));
                            if (! array_key_exists($initialSettingsTab, $connectionTabs)) {
                                $initialSettingsTab = 'gigachat';
                            }

                            foreach ($settingsTabErrors as $tabCode => $hasErrors) {
                                if ($hasErrors) {
                                    $initialSettingsTab = $tabCode;
                                    break;
                                }
                            }
                        @endphp

                        <input type="hidden" id="settings-tab" name="settings_tab" value="{{ $initialSettingsTab }}">

                        <ul class="nav nav-tabs ai-settings-tabs mt-4" id="ai-settings-tabs" role="tablist">
                            @foreach ($connectionTabs as $tabCode => $tab)
                                <li class="nav-item" role="presentation">
                                    <button
                                        class="nav-link {{ $initialSettingsTab === $tabCode ? 'active' : '' }}"
                                        id="{{ $tabCode }}-settings-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#{{ $tabCode }}-settings-pane"
                                        data-settings-tab="{{ $tabCode }}"
                                        type="button"
                                        role="tab"
                                        aria-controls="{{ $tabCode }}-settings-pane"
                                        aria-selected="{{ $initialSettingsTab === $tabCode ? 'true' : 'false' }}"
                                    >
                                        <i class="bi {{ $tab['icon'] }} me-1" aria-hidden="true"></i>
                                        {{ $tab['label'] }}
                                        @if ($settingsTabErrors[$tabCode])
                                            <span class="badge rounded-pill text-bg-danger ms-1">!</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content border border-top-0 rounded-bottom p-3 p-lg-4" id="ai-settings-tab-content">
                            @foreach ($connectionTabs as $tabCode => $tab)
                                <div
                                    class="tab-pane fade {{ $initialSettingsTab === $tabCode ? 'show active' : '' }}"
                                    id="{{ $tabCode }}-settings-pane"
                                    role="tabpanel"
                                    aria-labelledby="{{ $tabCode }}-settings-tab"
                                    tabindex="0"
                                >
                                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2 mb-3">
                                        <div>
                                            <h5 class="h6 fw-bold mb-1">Подключение {{ $tab['label'] }}</h5>
                                            <div class="small text-body-secondary">Параметры API и зашифрованные учётные данные хранятся в базе данных.</div>
                                        </div>
                                        <span class="badge text-bg-light border text-body ms-sm-auto">Секреты повторно не отображаются</span>
                                    </div>

                                    @if (isset($tab['notice']))
                                        <div class="alert alert-warning py-2 small" role="note">
                                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                                            {{ $tab['notice'] }}
                                        </div>
                                    @endif

                                    <div class="row g-3">
                                        @foreach ($tab['fields'] as $field)
                                            @php($fieldId = str_replace('_', '-', $field['name']))
                                            <div class="{{ $field['column'] }}">
                                                <label class="form-label fw-semibold" for="{{ $fieldId }}">
                                                    {{ $field['label'] }}
                                                    @if ($field['required'] ?? false)<span class="text-danger">*</span>@endif
                                                </label>
                                                @if (isset($field['suffix']))
                                                    <div class="input-group">
                                                @endif
                                                <input
                                                    class="form-control @error($field['name']) is-invalid @enderror"
                                                    type="{{ $field['type'] }}"
                                                    id="{{ $fieldId }}"
                                                    name="{{ $field['name'] }}"
                                                    value="{{ old($field['name'], $aiSettings[$tabCode][$field['key']] ?? '') }}"
                                                    @if (isset($field['min'])) min="{{ $field['min'] }}" @endif
                                                    @if (isset($field['max'])) max="{{ $field['max'] }}" @endif
                                                    @if ($field['required'] ?? false) required @endif
                                                    @cannot('manage-settings') disabled @endcannot
                                                >
                                                @if (isset($field['suffix']))
                                                        <span class="input-group-text">{{ $field['suffix'] }}</span>
                                                @endif
                                                @error($field['name'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                @if (isset($field['suffix']))
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach

                                        @foreach ($tab['switches'] as $switch)
                                            @php($switchId = str_replace('_', '-', $switch['name']))
                                            <div class="col-md-6">
                                                <div class="settings-option rounded border bg-body-tertiary p-3 h-100">
                                                    <div class="form-check form-switch mb-1">
                                                        @if ($switch['locked'] ?? false)
                                                            <input type="hidden" name="{{ $switch['name'] }}" value="1">
                                                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $switchId }}" name="{{ $switch['name'] }}" value="1" checked disabled>
                                                        @else
                                                            <input class="form-check-input @error($switch['name']) is-invalid @enderror" type="checkbox" role="switch" id="{{ $switchId }}" name="{{ $switch['name'] }}" value="1" @checked(old($switch['name'], $aiSettings[$tabCode][$switch['key']] ?? false)) @cannot('manage-settings') disabled @endcannot>
                                                        @endif
                                                        <label class="form-check-label fw-semibold" for="{{ $switchId }}">{{ $switch['label'] }}</label>
                                                        @error($switch['name'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="form-text mt-0">{{ $switch['help'] }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <i class="bi bi-key text-primary" aria-hidden="true"></i>
                                        <h6 class="fw-bold mb-0">Учётные данные</h6>
                                    </div>

                                    <div class="alert alert-info py-2 small" role="note">
                                        Оставьте поле пустым, чтобы сохранить текущее значение. Введённый секрет заменит сохранённое значение.
                                    </div>

                                    @if ($aiSettings[$tabCode]['credentials_decryption_error'] ?? false)
                                        <div class="alert alert-danger py-2 small" role="alert">
                                            <strong>Не удалось расшифровать сохранённые учётные данные.</strong>
                                            Введите новые значения либо очистите повреждённые данные после переключения активного провайдера.
                                        </div>
                                    @endif

                                    <div class="row g-3">
                                        @foreach ($tab['credentials'] as $credential)
                                            @php($credentialId = str_replace('_', '-', $credential['name']))
                                            <div class="{{ count($tab['credentials']) === 1 ? 'col-lg-6' : (count($tab['credentials']) === 2 ? 'col-lg-6' : 'col-lg-4') }}">
                                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                                    <label class="form-label fw-semibold mb-0" for="{{ $credentialId }}">{{ $credential['label'] }}</label>
                                                    @if ($aiSettings[$tabCode][$credential['state']] ?? false)
                                                        <span class="badge text-bg-success">Сохранён</span>
                                                    @else
                                                        <span class="badge text-bg-warning">Не задан</span>
                                                    @endif
                                                </div>
                                                <input
                                                    class="form-control @error($credential['name']) is-invalid @enderror"
                                                    type="password"
                                                    id="{{ $credentialId }}"
                                                    name="{{ $credential['name'] }}"
                                                    autocomplete="new-password"
                                                    placeholder="Введите новое значение"
                                                    @cannot('manage-settings') disabled @endcannot
                                                >
                                                @error($credential['name'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="form-check mt-3">
                                        <input class="form-check-input @error($tab['clear_name']) is-invalid @enderror" type="checkbox" id="{{ str_replace('_', '-', $tab['clear_name']) }}" name="{{ $tab['clear_name'] }}" value="1" @checked(old($tab['clear_name'])) @cannot('manage-settings') disabled @endcannot>
                                        <label class="form-check-label text-danger fw-semibold" for="{{ str_replace('_', '-', $tab['clear_name']) }}">{{ $tab['clear_label'] }}</label>
                                        @error($tab['clear_name'])<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">Перед удалением переключите активный провайдер на другой режим.</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <hr class="my-4">

                    <section aria-labelledby="processing-settings-heading">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="settings-section-icon text-bg-primary">
                                <i class="bi bi-gear-wide-connected" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h4 class="h6 fw-bold mb-0" id="processing-settings-heading">Параметры обработки</h4>
                                <div class="small text-body-secondary">Ограничения актуальности и объединения материалов.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="max-news-age-hours">
                                    Максимальный возраст новости <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                    <input class="form-control @error('max_news_age_hours') is-invalid @enderror" type="number" id="max-news-age-hours" name="max_news_age_hours" value="{{ old('max_news_age_hours', $settings['max_news_age_hours']) }}" min="1" max="8760" required @cannot('manage-settings') disabled @endcannot>
                                    <span class="input-group-text">часов</span>
                                    @error('max_news_age_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">Более старые публикации не проходят проверку актуальности.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="event-similarity-threshold">
                                    Порог сходства событий <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-intersect" aria-hidden="true"></i></span>
                                    <input class="form-control @error('event_similarity_threshold') is-invalid @enderror" type="number" id="event-similarity-threshold" name="event_similarity_threshold" value="{{ old('event_similarity_threshold', $settings['event_similarity_threshold']) }}" min="0" max="1" step="0.01" required @cannot('manage-settings') disabled @endcannot>
                                    <span class="input-group-text">0–1</span>
                                    @error('event_similarity_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">Материалы с равным или большим сходством считаются одним событием.</div>
                            </div>
                        </div>
                    </section>
                </div>

                @can('manage-settings')
                <div class="card-footer bg-body-tertiary">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-floppy-fill me-1" aria-hidden="true"></i>
                            Сохранить настройки
                        </button>
                    </div>
                </div>
                @endcan
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .settings-card .card-header {
        padding: 1rem 1.25rem;
    }
    .settings-card .card-title {
        float: none;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .settings-section-icon,
    .settings-option-icon {
        align-items: center;
        display: inline-flex;
        flex: 0 0 auto;
        justify-content: center;
    }
    .settings-section-icon {
        border-radius: .5rem;
        height: 2.25rem;
        width: 2.25rem;
    }
    .settings-option-icon {
        border-radius: 50%;
        height: 2.5rem;
        width: 2.5rem;
    }
    .settings-option {
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .ai-provider-option {
        cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .ai-provider-option:has(.ai-provider-checkbox:checked) {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
    }
    .ai-provider-option:has(.ai-provider-checkbox:disabled) {
        cursor: not-allowed;
    }
    .ai-settings-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
    }
    .ai-settings-tabs .nav-link {
        white-space: nowrap;
    }
    .settings-option:focus-within {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
    }
    .settings-card .form-switch .form-check-input {
        cursor: pointer;
    }
    .settings-card .form-switch .form-check-input:disabled {
        cursor: not-allowed;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const providerCheckboxes = Array.from(document.querySelectorAll('.ai-provider-checkbox:not(:disabled)'));

        providerCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    providerCheckboxes.forEach((otherCheckbox) => {
                        if (otherCheckbox !== checkbox) {
                            otherCheckbox.checked = false;
                        }
                    });

                    return;
                }

                if (!providerCheckboxes.some((providerCheckbox) => providerCheckbox.checked)) {
                    checkbox.checked = true;
                }
            });
        });

        const settingsTabInput = document.getElementById('settings-tab');
        const settingsTabButtons = Array.from(document.querySelectorAll('[data-settings-tab]'));
        const settingsTabErrors = @json($settingsTabErrors);
        const tabWithErrors = Object.keys(settingsTabErrors).find((tabCode) => settingsTabErrors[tabCode]);
        const requestedTab = tabWithErrors || settingsTabInput?.value || 'gigachat';
        const requestedTabButton = settingsTabButtons.find((button) => button.dataset.settingsTab === requestedTab);

        if (requestedTabButton && window.bootstrap?.Tab) {
            window.bootstrap.Tab.getOrCreateInstance(requestedTabButton).show();
        }

        settingsTabButtons.forEach((button) => {
            button.addEventListener('shown.bs.tab', () => {
                if (settingsTabInput) {
                    settingsTabInput.value = button.dataset.settingsTab;
                }
            });
        });
    });
</script>
@endpush
