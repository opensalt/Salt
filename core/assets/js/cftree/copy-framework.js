import { spinner } from '../util-salt';

export default function(apx){
    apx.copyFramework = {
        init() {
            $("#copyFrameworkModal_copyLeftBtn").on('click', function(e){
                e.preventDefault();
                apx.copyFramework.copyFrameworkToLeft();
            });
            $("#copyFrameworkModal_copyRightBtn").on('click', function(e){
                e.preventDefault();
                apx.copyFramework.copyFrameworkToRight();
            });
            $("#copyFrameworkForm").on('submit', function(e){
                e.preventDefault();
                apx.copyFramework.copyFrameworkRequest();
            });
        },

        copyFrameworkRequest() {
            $("#copyFrameworkModal .file-loading .row .col-md-12").html(spinner("Copying Document"));
            $("#copyFrameworkModal .contentModal").addClass("hidden");
            $("#copyFrameworkModal .file-loading").removeClass("hidden");

            let selectedDoc = $('#js-framework-to-copy').val();

            let sourceDoc= apx.lsDocId;
            let destinationDoc = selectedDoc;
            if ($("#copyFrameworkModal_copyLeftBtn").hasClass('active')) {
                sourceDoc = selectedDoc;
                destinationDoc = apx.lsDocId;
            }

            $.post(apx.path.doc_copy.replace('ID', sourceDoc), {
                copyToFramework: destinationDoc,
                type: ($("#copyType").is(":checked") ? 'copy' : 'copyAndAssociate')
            }, function(data){
                apx.copyFramework.copyFrameworkRequestSuccess(data);
            })
            .fail(function(data){
                apx.copyFramework.copyFrameworkRequestFail(data);
            });
        },

        copyFrameworkRequestSuccess(data) {
            $("#copyFrameworkModal .alert-success").find("a.js-docDestination")
                .attr("href", apx.path.lsDoc.replace('ID', data.docDestinationId));
            $("#copyFrameworkModal .alert-success").removeClass("hidden");
            $("#copyFrameworkModal .file-loading").addClass("hidden");
            apx.copyFramework.resetModalAfterRequest();
        },

        copyFrameworkRequestFail(data) {
            $("#copyFrameworkModal .alert-danger").removeClass("hidden");
            $("#copyFrameworkModal .file-loading").addClass("hidden");
            apx.copyFramework.resetModalAfterRequest();
        },

        copyFrameworkToLeft() {
            $("#copyFrameworkModal_copyLeftBtn").addClass('active');
            $("#copyFrameworkModal_copyRightBtn").removeClass('active');
        },

        copyFrameworkToRight() {
            $("#copyFrameworkModal_copyRightBtn").addClass('active');
            $("#copyFrameworkModal_copyLeftBtn").removeClass('active');
        },

        resetModalAfterRequest(data) {
            setTimeout(apx.copyFramework.resetModal, 3000);
        },

        resetModal(data) {
            $("#copyFrameworkModal .alert-success").addClass("hidden");
            $("#copyFrameworkModal .alert-danger").addClass("hidden");
            $("#copyFrameworkModal .file-loading").addClass("hidden");
            $("#copyFrameworkModal .contentModal").removeClass("hidden");
        }
    };
};
