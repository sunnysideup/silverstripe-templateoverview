const SmokeTester = {
  useJSTest: false,

  totalResponseTime: 0,

  numberOfTests: 0,

  numberOfTestsDone: 0,

  nextItemRetrieved: false,

  numberOfErrors: 0,

  list: Array.from(document.querySelectorAll('.checker-list .link-item')),

  baseURL: 'admin/templateoverviewsmoketestresponse/testone/',

  item: null,

  stop: true,

  init: function () {
    document.getElementById('NumberOfTests').textContent =
      SmokeTester.list.length
    document.querySelector('a.start').addEventListener('click', function () {
      console.log('start')
      if (SmokeTester.stop === true) {
        this.textContent = 'Stop'
        SmokeTester.stop = false

        if (!SmokeTester.item) {
          SmokeTester.item = SmokeTester.list.shift()
        }

        if (SmokeTester.item) {
          console.log('check', SmokeTester.item)
          SmokeTester.checkURL()
        } else {
          this.classList.add('disabled')
          this.textContent = 'Complete'
        }
      } else {
        this.textContent = 'Start'
        SmokeTester.stop = true
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
    const localT = Math.min(
      1,
      ((seconds % (10 / numSteps)) / (10 / numSteps)) * 1.5
    )

    return interpolateColor(colorStops[step], colorStops[nextStep], localT)
  },

  getContrastingColor: function (hexColor) {
    hexColor = hexColor.replace('#', '')

    const r = parseInt(hexColor.substring(0, 2), 16)
    const g = parseInt(hexColor.substring(2, 4), 16)
    const b = parseInt(hexColor.substring(4, 6), 16)

    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255

    return luminance > 0.5 ? '#000000' : '#FFFFFF'
  },

  baseLink: '',

  currentSortDirection: 'asc',

  currentSortSelection: '',

  sortTable: function (tdSelector) {
    if (SmokeTester.currentSortSelection === tdSelector) {
      if (SmokeTester.currentSortDirection === 'asc') {
        SmokeTester.currentSortDirection = 'desc'
      } else if (SmokeTester.currentSortDirection === 'desc') {
        SmokeTester.currentSortDirection = 'asc'
      }
    } else {
      SmokeTester.currentSortDirection = 'asc'
      SmokeTester.currentSortSelection = tdSelector
    }
    const table = document.querySelector('table')
    const tbody = table.querySelector('tbody')
    const rows = Array.from(tbody.querySelectorAll('tr'))

    rows.sort((rowA, rowB) => {
      const timeA = parseFloat(rowA.querySelector(tdSelector).textContent)
      const timeB = parseFloat(rowB.querySelector(tdSelector).textContent)
      if (SmokeTester.currentSortDirection === 'asc') {
        return timeA - timeB
      } else {
        return timeB - timeA
      }
    })

    rows.forEach(row => tbody.appendChild(row))
  },
  checkURL: function () {
    console.log('checkURL function called')
    if (!SmokeTester.stop) {
      SmokeTester.nextItemRetrieved = false
      const linkItem = SmokeTester.item
      console.log('Link item:', linkItem)
      let data = {}
      if (SmokeTester.useJSTest) {
        SmokeTester.baseLink = SmokeTester.item.dataset.link
        console.log('Using JS test. Base link:', SmokeTester.baseLink)
      } else {
        SmokeTester.baseLink = SmokeTester.baseURL
        console.log('Using base URL:', SmokeTester.baseLink)
        const isCMSLink = linkItem.dataset.isCmsLink
        const testLink = linkItem.dataset.link
        data = {
          test: testLink,
          iscmslink: isCMSLink,
          unique: new Date().getTime(),
          ajax: 1
        }
        console.log('Data object constructed:', data)
      }

      const rowID = linkItem.getAttribute('ID')
      const tableRow = document.getElementById(rowID)
      console.log('Row ID:', rowID, 'Table row:', tableRow)
      tableRow.classList.add('loading')
      fetch(`${SmokeTester.baseLink}?${new URLSearchParams(data)}`, {
        method: 'GET',
        timeout: 30000
      })
        .then(response => response.text())
        .then(data => {
          console.log('Fetch response received')
          let jsonData = null
          if (data.length > 1) {
            jsonData = JSON.parse(data)
            console.log('Parsed JSON data:', jsonData)

            if (jsonData.status !== 'success') {
              SmokeTester.numberOfErrors++
              console.log(
                'Error detected. Total errors:',
                SmokeTester.numberOfErrors
              )
            }
            tableRow.classList.remove('loading')
            tableRow.classList.add(jsonData.status)

            tableRow.querySelector('td.http-response').textContent =
              jsonData.httpResponse
            tableRow.querySelector('td.w3-check').textContent =
              jsonData.w3Content
            tableRow.querySelector('td.content').textContent = jsonData.content

            if (jsonData.responseTime) {
              const bgColour = SmokeTester.getResponseColor(
                jsonData.responseTime
              )
              const fontColour = SmokeTester.getContrastingColor(bgColour)
              SmokeTester.numberOfTestsDone++
              console.log(
                'Response time:',
                jsonData.responseTime,
                'Background color:',
                bgColour,
                'Font color:',
                fontColour
              )
              tableRow.querySelector('td.response-time').textContent =
                jsonData.responseTime
              tableRow.querySelector('td.response-time').style.backgroundColor =
                bgColour
              tableRow.querySelector('td.response-time').style.color =
                fontColour

              const errorRate =
                (
                  SmokeTester.numberOfErrors / SmokeTester.numberOfTestsDone
                ).toFixed(1) + '%'
              const responseTimeRounded = (
                SmokeTester.totalResponseTime / SmokeTester.numberOfTestsDone
              ).toFixed(2)
              SmokeTester.totalResponseTime += jsonData.responseTime

              console.log(
                'Updated metrics - Tests done:',
                SmokeTester.numberOfTestsDone,
                'Avg response time:',
                responseTimeRounded,
                'Error rate:',
                errorRate
              )

              document.getElementById('NumberOfTestsDone').textContent =
                SmokeTester.numberOfTestsDone
              document.getElementById('AverageResponseTime').textContent =
                responseTimeRounded
              document.getElementById('NumberOfErrors').textContent =
                SmokeTester.numberOfErrors
              document.getElementById('ErrorRate').textContent = errorRate
            }
          } else {
            SmokeTester.numberOfErrors++
            console.log(
              'Data length <= 1, marking as error. Total errors:',
              SmokeTester.numberOfErrors
            )
            tableRow.classList.remove('loading')
            tableRow.classList.add('error')
            tableRow.querySelector('td.content').innerHTML = 'Error'
          }
          SmokeTester.runNextItem()
        })
        .catch(error => {
          console.log('Fetch error:', error)

          tableRow.classList.remove('loading')
          tableRow.classList.add('error')
          tableRow.querySelector('td.content').innerHTML = 'Error: ' + error

          SmokeTester.runNextItem()
        })
    } else {
      console.log('checkURL stopped as SmokeTester.stop is true')
    }
  },

  runNextItem: function () {
    if (!SmokeTester.nextItemRetrieved) {
      SmokeTester.item = null
      SmokeTester.item = SmokeTester.list.shift()
      console.log('Next item in list:', SmokeTester.item)
      SmokeTester.nextItemRetrieved = true
    }
    if (SmokeTester.item) {
      console.log('Setting timeout for next checkURL call')
      setTimeout(() => {
        SmokeTester.checkURL()
      }, 10)
    } else {
      console.log('No more items. Process complete.')
      document.querySelector('a.start').classList.add('disabled')
      document.querySelector('a.start').textContent = 'Complete'
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  SmokeTester.init()
})
