const checker = {
  useJSTest: false,

  totalResponseTime: 0,

  numberOfTests: 0,

  numberOfTestsDone: 0,

  numberOfErrors: 0,

  list: Array.from(document.querySelectorAll('.checker-list .link-item')),

  baseURL: 'admin/templateoverviewsmoketestresponse/testone/',

  item: null,

  stop: true,

  init: function () {
    document.getElementById('NumberOfTests').textContent = checker.list.length
    document.querySelector('a.start').addEventListener('click', function () {
      if (checker.stop === true) {
        this.textContent = 'Stop'
        checker.stop = false

        if (!checker.item) {
          checker.item = checker.list.shift()
        }

        if (checker.item) {
          checker.checkURL()
        } else {
          this.classList.add('disabled')
          this.textContent = 'Complete'
        }
      } else {
        this.textContent = 'Start'
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

  sortTable: function (tdSelector) {
    if (checker.list.length === checker.numberOfTestsDone) {
      const table = document.querySelector('table')
      const tbody = table.querySelector('tbody')
      const rows = Array.from(tbody.querySelectorAll('tr'))

      rows.sort((rowA, rowB) => {
        const timeA = parseFloat(rowA.querySelector(tdSelector).textContent)
        const timeB = parseFloat(rowB.querySelector(tdSelector).textContent)
        return timeA - timeB
      })

      rows.forEach(row => tbody.appendChild(row))
    }
  },

  checkURL: function () {
    if (!checker.stop) {
      const linkItem = checker.item
      let data = {}
      if (checker.useJSTest) {
        checker.baseLink = checker.item.dataset.link
      } else {
        checker.baseLink = checker.baseURL
        const isCMSLink = linkItem.dataset.isCmsLink
        const testLink = linkItem.dataset.link
        data = {
          test: testLink,
          iscmslink: isCMSLink,
          unique: new Date().getTime()
        }
      }

      const rowID = linkItem.getAttribute('ID')
      const tableRow = document.getElementById(rowID)
      tableRow.classList.add('loading')

      fetch(`${checker.baseLink}?${new URLSearchParams(data)}`, {
        method: 'GET',
        timeout: 30000
      })
        .then(response => response.text())
        .then(data => {
          checker.item = null
          checker.item = checker.list.shift()

          let jsonData = null
          if (data.length > 1) {
            jsonData = JSON.parse(data)

            if (jsonData.status !== 'success') {
              checker.numberOfErrors++
            }
            tableRow.classList.remove('loading')
            tableRow.classList.add(jsonData.status)

            tableRow.querySelector('td.http-response').textContent =
              jsonData.httpResponse
            tableRow.querySelector('td.w3-check').textContent =
              jsonData.w3Content
            tableRow.querySelector('td.content').textContent = jsonData.content

            if (jsonData.responseTime) {
              const bgColour = checker.getResponseColor(jsonData.responseTime)
              const fontColour = checker.getContrastingColor(bgColour)
              checker.numberOfTestsDone++
              tableRow.querySelector('td.response-time').textContent =
                jsonData.responseTime
              tableRow.querySelector('td.response-time').style.backgroundColor =
                bgColour
              tableRow.querySelector('td.response-time').style.color =
                fontColour

              const errorRate =
                (checker.numberOfErrors / checker.numberOfTestsDone).toFixed(
                  1
                ) + '%'
              const responseTimeRounded = (
                checker.totalResponseTime / checker.numberOfTestsDone
              ).toFixed(2)
              checker.totalResponseTime += jsonData.responseTime

              document.getElementById('NumberOfTestsDone').textContent =
                checker.numberOfTestsDone
              document.getElementById('AverageResponseTime').textContent =
                responseTimeRounded
              document.getElementById('NumberOfErrors').textContent =
                checker.numberOfErrors
              document.getElementById('ErrorRate').textContent = errorRate
            }
          } else {
            checker.numberOfErrors++
            tableRow.classList.remove('loading')
            tableRow.classList.add('error')
            tableRow.querySelector('td.content').innerHTML = 'Error'
          }

          if (checker.item) {
            setTimeout(() => {
              checker.checkURL()
            }, 10)
          } else {
            document.querySelector('a.start').classList.add('disabled')
            document.querySelector('a.start').textContent = 'Complete'
          }
        })
        .catch(() => {
          checker.item = checker.list.shift()

          tableRow.classList.remove('loading')
          tableRow.classList.add('error')
          tableRow.querySelector('td.content').innerHTML = 'Error'

          if (checker.item) {
            setTimeout(() => {
              checker.checkURL()
            }, 10)
          } else {
            document.querySelector('a.start').classList.add('disabled')
            document.querySelector('a.start').textContent = 'Complete'
          }
        })
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  checker.init()
})
