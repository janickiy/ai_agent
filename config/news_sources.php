<?php

declare(strict_types=1);

return [
    'classes' => [
        'official_federal' => 'Федеральный официальный источник',
        'official_regional' => 'Региональный официальный источник',
        'state_company' => 'Государственная компания',
        'federal_media' => 'Федеральное СМИ',
        'business_media' => 'Деловое СМИ',
        'industry_media' => 'Отраслевое СМИ',
    ],

    /*
     * Каталог из согласованного макета раздела «Источники».
     * feed_url оставлен пустым: сайт показывается в каталоге, а мониторинг
     * запускается только после добавления и проверки RSS/Atom-ленты.
     */
    'sources' => [
        ['name' => 'Официальное опубликование правовых актов', 'domain' => 'publication.pravo.gov.ru', 'source_class' => 'official_federal', 'trust_score' => 100, 'poll_interval_minutes' => 30],
        ['name' => 'Росстат', 'domain' => 'rosstat.gov.ru', 'source_class' => 'official_federal', 'trust_score' => 100, 'poll_interval_minutes' => 30],
        ['name' => 'Росреестр', 'domain' => 'rosreestr.gov.ru', 'source_class' => 'official_federal', 'trust_score' => 100, 'poll_interval_minutes' => 30],
        ['name' => 'Росавтодор', 'domain' => 'rosavtodor.gov.ru', 'source_class' => 'official_federal', 'trust_score' => 100, 'poll_interval_minutes' => 30],
        ['name' => 'Минтранс России', 'domain' => 'mintrans.gov.ru', 'source_class' => 'official_federal', 'trust_score' => 100, 'poll_interval_minutes' => 30],
        ['name' => 'Минстрой России', 'domain' => 'minstroyrf.gov.ru', 'source_class' => 'official_federal', 'trust_score' => 100, 'poll_interval_minutes' => 30],
        ['name' => 'Правительство России', 'domain' => 'government.ru', 'source_class' => 'official_federal', 'trust_score' => 100, 'poll_interval_minutes' => 30],
        ['name' => 'Минстрой Московской области', 'domain' => 'minstroy.mosreg.ru', 'source_class' => 'official_regional', 'trust_score' => 95, 'poll_interval_minutes' => 30],
        ['name' => 'Правительство Москвы', 'domain' => 'mos.ru', 'source_class' => 'official_regional', 'trust_score' => 95, 'poll_interval_minutes' => 30],
        ['name' => 'Стройкомплекс Москвы', 'domain' => 'stroi.mos.ru', 'source_class' => 'official_regional', 'trust_score' => 95, 'poll_interval_minutes' => 30],
        ['name' => 'Транспорт Москвы', 'domain' => 'transport.mos.ru', 'source_class' => 'official_regional', 'trust_score' => 95, 'poll_interval_minutes' => 30],
        ['name' => 'Минтранс Московской области', 'domain' => 'mostrans.gov.ru', 'source_class' => 'official_regional', 'trust_score' => 95, 'poll_interval_minutes' => 30],
        ['name' => 'Правительство Санкт-Петербурга', 'domain' => 'gov.spb.ru', 'source_class' => 'official_regional', 'trust_score' => 95, 'poll_interval_minutes' => 30],
        ['name' => 'Росатом', 'domain' => 'rosatom.ru', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'Интерфакс', 'domain' => 'interfax.ru', 'source_class' => 'federal_media', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'Ростех', 'domain' => 'rostec.ru', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'ВЭБ.РФ', 'domain' => 'veb.ru', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'РЖД', 'domain' => 'company.rzd.ru', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'Автодор', 'domain' => 'russianhighways.ru', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'Строим.ДОМ.РФ', 'domain' => 'строим.дом.рф', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'Наш.ДОМ.РФ', 'domain' => 'наш.дом.рф', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'ДОМ.РФ', 'domain' => 'дом.рф', 'source_class' => 'state_company', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'РИА Новости', 'domain' => 'ria.ru', 'source_class' => 'federal_media', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'ТАСС', 'domain' => 'tass.ru', 'source_class' => 'federal_media', 'trust_score' => 90, 'poll_interval_minutes' => 60],
        ['name' => 'Российская газета', 'domain' => 'rg.ru', 'source_class' => 'business_media', 'trust_score' => 85, 'poll_interval_minutes' => 60],
        ['name' => 'Ведомости', 'domain' => 'vedomosti.ru', 'source_class' => 'business_media', 'trust_score' => 85, 'poll_interval_minutes' => 60],
        ['name' => 'РБК Недвижимость', 'domain' => 'realty.rbc.ru', 'source_class' => 'business_media', 'trust_score' => 85, 'poll_interval_minutes' => 60],
        ['name' => 'РБК', 'domain' => 'rbc.ru', 'source_class' => 'business_media', 'trust_score' => 85, 'poll_interval_minutes' => 60],
        ['name' => 'Коммерсантъ', 'domain' => 'kommersant.ru', 'source_class' => 'business_media', 'trust_score' => 85, 'poll_interval_minutes' => 60],
        ['name' => 'ЕРЗ.РФ', 'domain' => 'erzrf.ru', 'source_class' => 'industry_media', 'trust_score' => 70, 'poll_interval_minutes' => 60],
    ],
];
