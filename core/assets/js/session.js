let sessionModal = null;
let focus = null;
let started = false;

function showWarning(warning) {
    if (warning === 'info') {
        focus = document.activeElement;
    }

    if (!document.getElementById('sessionTimeoutModal')) {
        const template = `
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" role="dialog" aria-label="Session Timeout Dialogue">
   <div class="modal-dialog modal-lg" role="document">
       <div class="modal-content">
           <div class="modal-body text-center bg-info text-black">
               <h3>Your session will expire soon.</h3>
               <button class="btn btn-md btn-primary">Renew Session</button>
           </div>
       </div>
   </div>
</div>
`;

        const modalDiv = document.createElement('div');
        modalDiv.innerHTML = template;
        document.body.prepend(modalDiv);

        const modalButton = document.querySelector(`#sessionTimeoutModal button`);
        modalButton.addEventListener('click', function buttonListener(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            e.currentTarget.blur();
            renewSession();
        });
    }

    let classes = [];
    let message = '';
    switch (warning) {
        case 'expired':
            classes = ['modal-body', 'text-center', 'bg-danger', 'text-black'];
            message = 'Your session has expired.';
            document.querySelector(`#sessionTimeoutModal button`).classList.add('invisible');
            break;

        case 'warning-2':
            classes = ['modal-body', 'text-center', 'bg-danger', 'text-black'];
            message = 'Your session is about to expire.';
            break;

        case 'warning':
            classes = ['modal-body', 'text-center', 'bg-warning', 'text-black'];
            message = 'Your session is about to expire.';
            break;

        case 'info':
            classes = ['modal-body', 'text-center', 'bg-info', 'text-black'];
            message = 'Your session will expire soon.'
            break;
    }
    const sessionModalBody = document.querySelector('#sessionTimeoutModal .modal-body');
    sessionModalBody.classList.remove(...sessionModalBody.classList);
    sessionModalBody.classList.add(...classes);

    const sessionModalMessage = document.querySelector('#sessionTimeoutModal h3');
    sessionModalMessage.innerText = message;

    sessionModal = bootstrap.Modal.getOrCreateInstance(`#sessionTimeoutModal`, {
        backdrop: 'static',
        keyboard: false
    });
    sessionModal.show();
}

function removeWarning() {
    if (focus) {
        focus.focus();
    }

    if (sessionModal) {
        sessionModal.hide();
        sessionModal = null;
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
    if (started) {
        return;
    }
    started = true;

    if (document.getElementsByTagName('html')[0].classList.contains('no-auth')) {
        // Only check authenticated sessions

        return;
    }

    checkSession();
};

export default check;
