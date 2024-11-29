let sessionModal = null;

function addBackdrop() {
    if (!document.getElementById('sessionModalBackdrop')) {
        const style = document.createElement('style');
        style.id = 'sessionModalBackdrop';
        style.innerText = '#sessionTimeoutModal.in ~ .modal-backdrop { z-index: 1100; }';
        document.head.appendChild(style);
    }
}

function showWarning(warning) {
    console.log('show warning');
    let classes = '';
    let message = '';
    let button = '<button class="btn btn-md btn-primary">Renew Session</button>';

    switch (warning) {
        case 'expired':
            classes = 'bg-danger text-black';
            message = 'Your session has expired.';
            button = '';
            break;

        case 'warning-2':
            classes = 'bg-warning text-black';
            message = 'Your session is about to expired.';
            break;

        case 'warning':
            classes = 'bg-warning text-black';
            message = 'Your session is about to expired.';
            break;

        case 'info':
            classes = 'bg-info text-black';
            message = 'Your session will expire soon.'
            break;
    }

    const template = `
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" role="dialog" aria-label="Session Timeout Dialogue" style="z-index: 1110;">
   <div class="modal-dialog modal-lg" role="document">
       <div class="modal-content">
           <div class="modal-body ${classes} text-center">
               <h3>${message}</h3>
               ${button}
           </div>
       </div>
   </div>
</div>
`;

    addBackdrop();
    const modalDiv = document.createElement('div');
    modalDiv.innerHTML = template;
    document.body.prepend(modalDiv);

    document.querySelector('#sessionTimeoutModal button')
        .addEventListener('click', (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            renewSession();
        });

    removeWarning();

    sessionModal = new bootstrap.Modal('#sessionTimeoutModal', {
        backdrop: 'static',
        keyboard: false
    });
    sessionModal.show();
}

function removeWarning() {
    if (sessionModal) {
        sessionModal.hide();
        sessionModal.dispose();
        sessionModal = null;
        document.getElementById('sessionTimeoutModal').remove();
    }
}

function renewSession() {
    fetch('/session/renew')
        .then(json => json.json())
        .then((json) => {
            checkSession();
        })
        .catch(() => {
        });
}

function checkSession() {
    fetch('/session/check')
        .then(json => json.json())
        .then((json) => {
            let remainingTime = json.remainingTime;
            console.log('remaining time: ' + remainingTime);

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
        .catch(() => {
            // No session exists, or is expired
            showWarning('expired');
        });
}

const check = () => {
    if (document.getElementsByTagName('html')[0].classList.contains('no-auth')) {
        // Only check authenticated sessions

        return;
    }

    checkSession();
};

export default check;
