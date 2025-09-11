<div class="panel col-lg-12" style="margin-bottom: -40px;">
    <div class="panel-heading">
        <input type="text" id="pickupPointsTableSearch" placeholder="Search...">
    </div>
</div>
<script>
    $('#pickupPointsTableSearch').on('keyup', function(){
        const search = $(this).val();
        const rows = document.querySelectorAll('#table-sameday_pickup_points tbody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if(!text.includes(search)){
                row.style.display = 'none';
            }else{
                row.style.display = '';
            }
        });
    });
</script>