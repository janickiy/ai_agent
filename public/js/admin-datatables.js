(function () {
    'use strict';

    const language = {
        processing: 'Обработка...',
        search: '',
        searchPlaceholder: '',
        lengthMenu: 'Отображено _MENU_ записей на страницу',
        info: 'Показано с _START_ по _END_ из _TOTAL_ записей',
        infoEmpty: 'Записей нет',
        infoFiltered: '(отфильтровано из _MAX_ записей)',
        loadingRecords: 'Загрузка...',
        zeroRecords: 'Совпадений не найдено',
        emptyTable: 'Данные отсутствуют',
        paginate: {
            first: 'Первая',
            previous: 'Пред.',
            next: 'След.',
            last: 'Последняя',
        },
        aria: {
            orderable: 'Сортировать по столбцу',
            orderableReverse: 'Изменить направление сортировки',
        },
    };

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value === null || value === undefined ? '' : String(value);

        return element.innerHTML;
    }

    function create(selector, options) {
        document.querySelector(selector)?.classList.add('table-bordered');

        return new DataTable(selector, {
            processing: true,
            serverSide: true,
            autoWidth: false,
            pagingType: 'simple_numbers',
            searchDelay: 350,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language,
            ...options,
        });
    }

    function safeUrl(value) {
        try {
            const url = new URL(String(value));

            return ['http:', 'https:'].includes(url.protocol) ? url.href : '#';
        } catch {
            return '#';
        }
    }

    window.AdminDataTables = {
        create,
        escapeHtml,
        safeUrl,
    };
})();
