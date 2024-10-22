function addBackdrop() {
    if (0 === $('#sessionModalBackdrop').length) {
        $('<style id="sessionModalBackdrop">#sessionTimeoutModal.in ~ .modal-backdrop { z-index: 1100; }</style>').appendTo('head');
    }
}

function showWarning(warning) {
    let modal = $('#sessionTimeoutModal');

    if (0 === modal.length) {
        let template = `
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" role="dialog" aria-labelledby="sessionTimeoutLabel" style="z-index: 1110;">
   <div class="modal-dialog modal-lg" role="document">
       <div class="modal-content">
           <div class="modal-body bg-info text-info text-center">
               <h3>Your session is about to expire.</h3>
               <button class="btn btn-md btn-primary">Renew Session</button>
           </div>
       </div>
   </div>
</div>
`;
        addBackdrop();
        $('body').prepend(template);
        modal = $('#sessionTimeoutModal');
    }

    modal.find('.modal-body')
        .removeClass('bg-info text-info bg-warning text-warning bg-danger text-danger')
        .find('button').remove();

    switch (warning) {
        case 'expired':
            modal.find('.modal-body')
                .addClass('bg-danger text-danger')

                .find('h3')
                .html('Your session has expired.');
            break;

        case 'warning-2':
            modal.find('.modal-body')
                .addClass('bg-warning text-warning')

                .find('h3')
                .html('Your session is about to expire.')
            ;
            break;

        case 'warning':
            modal.find('.modal-body')
                .addClass('bg-warning text-warning')

                .find('h3')
                .html('Your session is about to expire.')
                .after('<button class="btn btn-md btn-primary">Renew Session</button>')
            ;
            break;

        case 'info':
            modal.find('.modal-body')
                .addClass('bg-info text-info')

                .find('h3')
                .html('Your session will expire soon.')
                .after('<button class="btn btn-md btn-primary">Renew Session</button>')
            ;
            break;
    }

    modal.find('.modal-body button')
        .one('click', (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            renewSession();
        });

    modal.modal({
        backdrop: 'static',
        keyboard: false
    });
}

function removeWarning() {
    $('#sessionTimeoutModal').modal('hide');
}

function renewSession() {
    $.getJSON('/session/renew')
        .done((json, textStatus, jqxhr) => {
            checkSession();
        })
        .fail((jqxhr, textStatus, error) => {
        });
}

function checkSession() {
    $.getJSON('/session/check')
        .done((json, textStatus, jqxhr) => {
            let remainingTime = json.remainingTime;

            if (1 > remainingTime) {
                showWarning('expired');

                return;
            }

            if (11 > remainingTime) {
                showWarning('warning-2');

                setTimeout(() => {
                        checkSession();
                    },
                    (remainingTime)*1000+100
                );

                return;
            }

            if (61 > remainingTime) {
                showWarning('warning');

                setTimeout(() => {
                        checkSession();
                    },
                    (remainingTime - 10)*1000+100
                );

                return;
            }

            if (301 > remainingTime) {
                showWarning('info');

                setTimeout(() => {
                        checkSession();
                    },
                    (remainingTime-60)*1000+100
                );

                return;
            }

            removeWarning();

            // Set timeout for remainingTime - 300 to re-check
            setTimeout(() => {
                    checkSession();
                },
                (remainingTime-300)*1000+100
            );
        })
        .fail((jqxhr, textStatus, error) => {
            // No session exists, or is expired
            showWarning('expired');
        });
}

const check = () => {
    if ($('html').hasClass('no-auth')) {
        // Only check authenticated sessions

        return;
    }

    checkSession();
};

export default check;
