$(document).ready(function() {
    $(function() {

        var start = moment().subtract(1, 'days');
        var end = moment();

        function cb(start, end) {
            $('#date_range span').html(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));

            // Set date start
            $('#start_date').val(start.format('YYYY-MM-DD'));

            // Set date end
            $('#end_date').val(end.format('YYYY-MM-DD'));

            // Set value if form is refreshed
            data_range = document.getElementById("date_range");
            original_date = data_range.getAttribute('value');
            
            $('#date_range').val(original_date); 
        }

        $('#date_range').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
               'Today': [moment(), moment()],
               'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               'Last 7 Days': [moment().subtract(6, 'days'), moment()],
               'Last 30 Days': [moment().subtract(29, 'days'), moment()],
               'This Month': [moment().startOf('month'), moment().endOf('month')],
               'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, cb);

        cb(start, end);
        
    });

    // Date Type button dropdown
    $(function(){
        $(".dropdown-menu li a").click(function(){
            $("#dateTypeDropdownBtn:first-child").text($(this).text());
            $("#dateTypeDropdownBtn:first-child").val($(this).text());
            $("#date_type").val($(this).text());
       });
    });

    // Reset button 
    $(function(){
        $("#reset").click(function(){
            $("#dateTypeDropdownBtn:first-child").text("None");
            $("#dateTypeDropdownBtn:first-child").val("None");
            $("#date_type").val("None");

            $("input:text").val('');
            $("select").val('');
       });
    });


    // Manufacturer filter
    $('#select-manufacturers').selectize({
        maxOptions: 50,
        // persist:false,
        create:true,
        valueField: 'label',
        labelField: 'label',
        searchField: ['label'],
        render: {
            option: function(item, escape) {
                return  '<div class="selectize-row">' +
                            '<span>' + escape(item.label) + '</span>' +
                        '</div>';
            }
        },

        // Get info from ajax when user is typing
        load: function(query, callback) {
            
            if (!query.length) return callback();
            $.ajax({
                url: '/admin/manufacturers/search/' + encodeURIComponent(query),
                type: 'GET',
                dataType: 'json',
                error: function() {
                    callback();
                },
                success: function(res) {
                    callback(res);
                }
            });
        },

        // Set users to vuejs data when user
        onChange:function(value) {
            // console.log('Selected: '  + this.options[value]);
        }
       
    });
    
    
});
