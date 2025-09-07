<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đổi Mã Hàng Hóa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-lg rounded-2xl w-full max-w-lg p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Đổi Mã Hàng Hóa</h2>

        @if (session('error'))
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4">
                {{ session('error') }}
            </div>
        @endif

        <form id="mahh-form" class="space-y-4">
            @csrf
            <div>
                <label for="old_code" class="block font-medium text-gray-700">Mã cũ:</label>
                <input type="text" class="mahh-autocomplete mt-1 w-full p-2 border rounded-lg" name="old_code"
                    id="old_code" required>
            </div>
            <div>
                <label for="new_code" class="block font-medium text-gray-700">Mã mới:</label>
                <input type="text" class="mahh-autocomplete mt-1 w-full p-2 border rounded-lg" name="new_code"
                    id="new_code" required>
            </div>
            <div>
                <label for="lenhsx" class="block font-medium text-gray-700">Lệnh sản xuất (có thể bỏ trống):</label>
                <input type="text" class="mt-1 w-full p-2 border rounded-lg" name="lenhsx" id="lenhsx">
            </div>
            <div class="flex justify-center">
                <button type="button" id="btn-preview"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Xem trước cập nhật
                </button>
            </div>
        </form>

        <!-- Modal -->
        <div id="confirm-modal"
            class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-md">
                <h4 class="text-lg font-semibold text-gray-800 mb-3">Bạn sắp cập nhật những mục sau:</h4>
                <ul id="log-list" class="list-disc pl-6 space-y-1 text-gray-700"></ul>
                <div class="flex justify-end gap-3 mt-6">
                    <button id="confirm-update"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Xác nhận</button>
                    <button type="button" onclick="document.getElementById('confirm-modal').classList.add('hidden')"
                        class="bg-gray-300 px-4 py-2 rounded-lg hover:bg-gray-400">Hủy</button>
                </div>
            </div>
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
                    document.getElementById('confirm-modal').classList.remove('hidden');
                });
        });

        document.getElementById('confirm-update').addEventListener('click', function() {
            const form = document.getElementById('mahh-form');
            form.setAttribute('action', '{{ route('updateMaHH') }}');
            form.setAttribute('method', 'POST');
            form.submit();
        });

        // Autocomplete
        $(function() {
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
</body>

</html>
