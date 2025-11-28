$(document).ready(function() {
  $('#showtimeTable').DataTable({
    language: {
      search: "🔍 Tìm kiếm:",
      lengthMenu: "Hiển thị _MENU_ dòng",
      info: "Hiển thị _START_ - _END_ / _TOTAL_ suất chiếu",
      paginate: { next: "▶", previous: "◀" },
      zeroRecords: "Không có dữ liệu phù hợp"
    },
    pageLength: 8,
    order: [[3, "desc"]]
  });
});
