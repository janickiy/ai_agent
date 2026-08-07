(function () {
    'use strict';

    const confirmedForms = new WeakSet();
    const pendingForms = new WeakSet();

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm-dialog]')) {
            return;
        }

        if (confirmedForms.has(form)) {
            return;
        }

        event.preventDefault();

        if (pendingForms.has(form)) {
            return;
        }

        if (!window.Swal || typeof window.Swal.fire !== 'function') {
            console.error('SweetAlert2 is unavailable; destructive action was cancelled.');

            return;
        }

        pendingForms.add(form);
        const submitter = event.submitter;

        window.Swal.fire({
            title: form.dataset.confirmTitle || 'Подтвердите действие',
            text: form.dataset.confirmText || 'Это действие нельзя отменить.',
            icon: 'warning',
            showCancelButton: true,
            reverseButtons: true,
            focusCancel: true,
            confirmButtonText: form.dataset.confirmButton || 'Удалить',
            cancelButtonText: 'Отмена',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            confirmedForms.add(form);

            if (submitter instanceof HTMLElement && form.contains(submitter)) {
                form.requestSubmit(submitter);

                return;
            }

            form.requestSubmit();
        }).finally(function () {
            pendingForms.delete(form);
        });
    });
})();
