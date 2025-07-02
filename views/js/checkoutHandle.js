document.addEventListener('DOMContentLoaded', function() {

    if(document.getElementById('nomenclator').value == 1) {
        let ajaxRoute = document.getElementById('ajaxRoute').value;
        let token = document.getElementById('token').value;

        let countyElement = document.getElementById('id_state');
        if(countyElement){
            countyElement.addEventListener("change", function () {
                let county = countyElement.value;
                $.ajax({
                    type: "POST",
                    url: ajaxRoute,
                    data: {
                        action: 'CitiesAjax',
                        county_id: county,
                        token: token
                    },
                    success: function (response) {
                        let arr = JSON.parse(response);
                        let html = '';
                        arr.forEach((item) => html += '<option value="' + item['city_name'] + '">' + item['city_name'] + '</option>');
                        $('[name="city"]').html(html);
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', error);
                    }
                });

            });
        }

        var cityInput = document.querySelector("input[name=\'city\']");
        if (cityInput) {
            var select = document.createElement('select');
            select.name = 'city';
            select.classList.add('form-control');

            var cities = ['Alege un judet'];
            cities.forEach(function(city) {
                var option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                select.appendChild(option);
            });

            cityInput.parentNode.replaceChild(select, cityInput);
        }
    }


});