if (
  document.getElementById('ClassList') !== null &&
  typeof document.getElementById('ClassList') !== 'undefined'
) {
  document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('ClassList')) {
      document.querySelectorAll('.typo-less').forEach(function (element) {
        element.style.display = 'none'
      })

      document
        .querySelectorAll('#ClassList .typo-seemore')
        .forEach(function (seemoreElement) {
          seemoreElement.addEventListener('click', function (event) {
            event.preventDefault()
            const url = seemoreElement.getAttribute('href')
            const id = seemoreElement.getAttribute('rel')
            const targetElement = document.getElementById(id)

            targetElement.style.display = 'block'
            targetElement.innerHTML = '<li>loading pages ....</li>'

            fetch(url)
              .then(response => response.text())
              .then(data => {
                targetElement.innerHTML = data
                // PrettyPhotoLoader.load("#" + id); // Uncomment if needed
              })

            seemoreElement.closest('.typo-more').style.display = 'none'
            seemoreElement.closest(
              '.typo-more'
            ).nextElementSibling.style.display = 'block'
          })
        })

      document
        .querySelectorAll('#ClassList .typo-seeless')
        .forEach(function (seelessElement) {
          seelessElement.addEventListener('click', function (event) {
            event.preventDefault()
            const id = seelessElement.getAttribute('rel')
            const targetElement = document.getElementById(id)

            targetElement.style.display = 'none'
            seelessElement.closest('.typo-less').style.display = 'none'
            seelessElement.closest(
              '.typo-less'
            ).previousElementSibling.style.display = 'block'
          })
        })
    }
  })
}
