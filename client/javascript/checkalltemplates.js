jQuery(document).ready(function () {
  const checker = {
    useJSTest: false,

    totalResponseTime: 0,

    numberOfTests: 0,

    numberOfTestsDone: 0,

    numberOfErrors: 0,

    list: jQuery('.checker-list .link-item').toArray(),

    baseURL: 'admin/templateoverviewsmoketestresponse/testone/',

    item: null,

    stop: true,

    init: function () {
      jQuery('#NumberOfTests').text(checker.list.length)
      jQuery('a.start').on('click', function () {
        if (checker.stop === true) {
          jQuery(this).text('Stop')
          checker.stop = false

          if (!checker.item) {
            checker.item = checker.list.shift()
          }

          if (checker.item) {
            checker.checkURL()
          } else {
            jQuery(this).addClass('disabled').text('Complete')
          }
        } else {
          jQuery(this).text('Start')
          checker.stop = true
        }
      })
    },

    getResponseColor: function (seconds) {
      const colorStops = [
        { r: 204, g: 255, b: 204 }, // Light green
        { r: 0, g: 100, b: 0 }, // Dark green
        { r: 173, g: 216, b: 230 }, // Light blue
        { r: 0, g: 0, b: 139 }, // Dark blue
        { r: 255, g: 255, b: 224 }, // Light yellow
        { r: 255, g: 215, b: 0 }, // Dark yellow
        { r: 255, g: 165, b: 0 }, // Orange
        { r: 255, g: 69, b: 0 }, // Red
        { r: 139, g: 0, b: 0 }, // Dark red
        { r: 0, g: 0, b: 0 } // Black
      ]

      function interpolateColor (start, end, factor) {
        const r = Math.round(start.r + factor * (end.r - start.r))
        const g = Math.round(start.g + factor * (end.g - start.g))
        const b = Math.round(start.b + factor * (end.b - start.b))
        return `rgb(${r},${g},${b})`
      }

      const numSteps = colorStops.length - 1
      const step = Math.floor((seconds / 10) * numSteps)
      const nextStep = step + 1

      // Shift transition earlier by slightly reducing time spent on each color
      const localT = Math.min(
        1,
        ((seconds % (10 / numSteps)) / (10 / numSteps)) * 1.5
      )

      return interpolateColor(colorStops[step], colorStops[nextStep], localT)
    },

    getContrastingColor: function (hexColor) {
      // Remove the '#' if it's there
      hexColor = hexColor.replace('#', '')

      // Convert hex to RGB
      const r = parseInt(hexColor.substring(0, 2), 16)
      const g = parseInt(hexColor.substring(2, 4), 16)
      const b = parseInt(hexColor.substring(4, 6), 16)

      // Calculate the luminance
      const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255

      // If the luminance is high, return black, else return white
      return luminance > 0.5 ? '#000000' : '#FFFFFF'
    },

    baseLink: '',

    sortTable: function (tdSelector) {
      if (checker.list.length === checker.numberOfTestsDone) {
        const table = document.getElementsByName('table')[0]
        const tbody = table.getElementsByTagName('tbody')[0]
        const rows = Array.from(tbody.getElementsByTagName('tr'))

        rows.sort((rowA, rowB) => {
          const timeA = parseFloat(rowA.querySelector(tdSelector).textContent)
          const timeB = parseFloat(rowB.querySelector(tdSelector).textContent)
          return timeA - timeB
        })

        // Append the sorted rows back to the tbody
        rows.forEach(row => tbody.appendChild(row))
      }
    },

    checkURL: function () {
      if (!checker.stop) {
        const linkItem = jQuery(checker.item)
        let data = {}
        if (checker.useJSTest) {
          checker.baseLink = checker.item.dataset.link
        } else {
          checker.baseLink = checker.baseURL
          const isCMSLink = linkItem.data('is-cms-link')
          const testLink = linkItem.data('link')
          data = {
            test: testLink,
            iscmslink: isCMSLink,
            unique: new Date().getTime()
          }
        }
        const rowID = linkItem.attr('ID')
        let tableRow = jQuery('#' + rowID)
        tableRow.addClass('loading')
        jQuery.ajax({
          url: checker.baseLink,
          type: 'get',
          data: data,
          success: function (data, textStatus) {
            checker.item = null

            checker.item = checker.list.shift()

            let jsonData = null
            if (data.length > 1) {
              jsonData = JSON.parse(data)

              if (jsonData.status !== 'success') {
                checker.numberOfErrors++
              }
              tableRow.removeClass('loading').addClass(jsonData.status)

              tableRow.find('td.http-response').text(jsonData.httpResponse)
              tableRow.find('td.w3-check').text(jsonData.w3Content)
              tableRow.find('td.content').text(jsonData.content)

              if (jsonData.responseTime) {
                const bgColour = checker.getResponseColor(jsonData.responseTime)
                const fontColour = checker.getContrastingColor(bgColour)
                checker.numberOfTestsDone++
                tableRow
                  .find('td.response-time')
                  .text(jsonData.responseTime)
                  .css('background-color', bgColour)
                  .css('color', fontColour)

                let errorRate =
                  checker.numberOfErrors / checker.numberOfTestsDone
                let errorRateRounded = Math.round(1000 * errorRate) / 10 + '%'
                let responseTime =
                  checker.totalResponseTime / checker.numberOfTestsDone
                let responseTimeRounded = Math.round(100 * responseTime) / 100
                checker.totalResponseTime =
                  checker.totalResponseTime + jsonData.responseTime

                jQuery('#NumberOfTestsDone').text(checker.numberOfTestsDone)
                jQuery('#AverageResponseTime').text(responseTimeRounded)
                jQuery('#NumberOfErrors').text(checker.numberOfErrors)
                jQuery('#ErrorRate').text(errorRateRounded)
              }
            } else {
              checker.numberOfErrors++
              tableRow
                .removeClass('loading')
                .addClass('error')
                .find('td.content')
                .html('Error')
            }

            if (checker.item) {
              window.setTimeout(function () {
                checker.checkURL()
              }, 10)
            } else {
              jQuery('a.start').addClass('disabled').text('Complete')
            }
          },
          error: function (error) {
            checker.item = checker.list.shift()

            tableRow
              .removeClass('loading')
              .addClass('error')
              .find('td.content')
              .html('Error')

            if (checker.item) {
              window.setTimeout(function () {
                checker.checkURL()
              }, 10)
            } else {
              jQuery('a.start').addClass('disabled').text('Complete')
            }
          },
          dataType: 'html'
        })
      }
    }
  }

  checker.init()
})
