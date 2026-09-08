<!-- A modal dialog containing a form -->
<style>
    dialog {
        width: 98%;
        height: 98%;
    }
</style>
<script src="{{ asset('sn_client_resources/js/axios.min.js') }}"></script>
<dialog id="favDialog">
    <form>
        <div>
            <button class="btn btn-sm btn-outline-dark mb-1" value="cancel" formmethod="dialog">Done</button>
        </div>
    </form>

    <iframe id="myIframe" src='{{ url('/') }}/sn_client_resources/admin/json-visual-editor/index.html' width="100%"
        height="100%">
        ...
    </iframe>

</dialog>

<output></output>
