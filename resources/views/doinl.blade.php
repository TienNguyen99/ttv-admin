<!DOCTYPE html>
<html>

<head>
    <title>Đổi Mã Hàng Hóa</title>
    <meta charset="UTF-8">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- jQuery UI -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
</head>

<body>
    <h2>Đổi Mã Hàng Hóa</h2>

    <div class="container">
        <h2>Cập nhật mã nguyên liệu</h2>

        @if (session('error'))
            <div style="color: red;">{{ session('error') }}</div>
        @endif

        <form id="mahh-form">
            @csrf
            <div>
                <label for="old_code">Mã cũ:</label>
                <input type="text" class="mahh-autocomplete" name="old_code" id="old_code" required>
            </div>
            <div>
                <label for="new_code">Mã mới:</label>
                <input type="text" class="mahh-autocomplete" name="new_code" id="new_code" required>
            </div>
            <button type="button" id="btn-preview">Xem trước cập nhật</button>
        </form>

        <!-- Modal xác nhận -->
        <div id="confirm-modal"
            style="display: none; background-color: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
            <h4>Bạn sắp cập nhật những mục sau:</h4>
            <ul id="log-list"></ul>
            <button id="confirm-update">Xác nhận cập nhật</button>
            <button onclick="document.getElementById('confirm-modal').style.display='none'">Hủy</button>
        </div>
    </div>

    <script>
        document.getElementById('btn-preview').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('mahh-form'));

            fetch('{{ route('checkUpdateMaHH') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'error') {
                        alert(data.message);
                        return;
                    }

                    const logList = document.getElementById('log-list');
                    logList.innerHTML = '';

                    let hasChanges = false;

                    for (const [table, count] of Object.entries(data.log)) {
                        if (count > 0) {
                            hasChanges = true;
                            const li = document.createElement('li');
                            li.textContent = `${table}: ${count} dòng`;
                            logList.appendChild(li);
                        }
                    }

                    if (!hasChanges) {
                        alert("Không có bảng nào bị ảnh hưởng.");
                        return;
                    }

                    document.getElementById('confirm-modal').style.display = 'block';
                });
        });

        document.getElementById('confirm-update').addEventListener('click', function() {
            const form = document.getElementById('mahh-form');
            form.setAttribute('action', '{{ route('updateMaNL') }}');
            form.setAttribute('method', 'POST');
            form.submit();
        });
    </script>
</body>
<script>
    $(function() {
        // Autocomplete
        $(".mahh-autocomplete").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "{{ route('mahh.suggest') }}",
                    dataType: "json",
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        response($.map(data, function(item) {
                            return {
                                label: item.Ma_hh + " - " + item.Ten_hh +
                                    " - " + item.Dvt,
                                value: item.Ma_hh
                            };
                        }));
                    }
                });
            },
            minLength: 2
        });


    });
</script>

</html>
